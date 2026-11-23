#!/usr/bin/env python3
"""Mangrove canopy detection on a drone raster with an Ultralytics YOLO segmentation model.

The model's classes are mangrove genera, and every detection is one canopy (crown). The script
does three things in one run over the raster:

* genus suggestion: which genera the mangrove pixels consist of (summarize). The pixels counted
  are those inside --mask (a U-Net mask from predict.py --raster) or, with --extent-out and no
  mask, inside the model's own extent;
* --extent-out: the union of all canopies found at >= --extent-conf as a 0/1 mask PNG on the
  raster's pixel grid, so the model can delineate the mangrove extent by itself (instead of the
  U-Net);
* --canopies-out: every canopy inside that extent pinpointed (genus, confidence, ground area, centre
  and outline in lat/lng; needs --bounds), so the map can show where each one is.

With --labels-out the per-pixel genus map is also saved, so --recount can count the genus inside
the owner's edited areas later without running the model. Prints JSON as the last stdout line,
like predict.py.
"""

from __future__ import annotations

import os
import sys
import tempfile

# Apache/PHP on Windows often starts this process without SystemRoot.
# Importing torch then crashes in asyncio/_overlapped with WinError 10106.
if os.name == "nt":
    system_root = os.environ.get("SYSTEMROOT") or os.environ.get("SystemRoot") or r"C:\Windows"
    os.environ.setdefault("SYSTEMROOT", system_root)
    os.environ.setdefault("SystemRoot", system_root)
    os.environ.setdefault("WINDIR", os.environ.get("WINDIR") or system_root)
    system32 = os.path.join(system_root, "System32")
    path = os.environ.get("PATH", "")
    if system32.lower() not in path.lower():
        os.environ["PATH"] = os.pathsep.join([system32, system_root, path])

# Ultralytics must not download anything or print progress (stdout carries the JSON result),
# and needs a writable settings folder even when the web server has no user profile.
os.environ.setdefault("YOLO_OFFLINE", "1")
os.environ.setdefault("YOLO_VERBOSE", "False")
os.environ.setdefault("YOLO_CONFIG_DIR", os.path.join(tempfile.gettempdir(), "mangrovemap-ultralytics"))

import argparse
import json
from pathlib import Path

import numpy as np
from PIL import Image, ImageDraw

sys.path.insert(0, str(Path(__file__).resolve().parent))  # predict_roboflow, the shared Roboflow client

# The hosted canopy Workflow (--roboflow): RF-DETR instance segmentation trained on mangrove-canopy v3.
DEFAULT_CANOPY_WORKFLOW = "mangrove-canopy-3-rfdetr-seg-small-t1-vmangrove-canopy-3-rfdetr-seg-small-t1-logic"
DEFAULT_CANOPY_CLASSES = "Avicennia,Nypa,Rhizophora,Sonneratia"


def load_model(weights_path: Path):
    from ultralytics import YOLO

    model = YOLO(str(weights_path))
    if model.task != "segment":
        raise RuntimeError(f"Expected a YOLO segmentation model, got task '{model.task}'.")
    names = model.names or {}
    if not names:
        raise RuntimeError("The model has no class names.")
    return model


def training_summary(model) -> dict | None:
    """Validation results Ultralytics saved in the checkpoint at the end of training, if any.

    Uses the mask (M) metrics: this is a segmentation model, so the masks are what it is used for.
    The dataset name is the folder of the data.yaml it was trained on (a Roboflow export is
    named "<project>-<version>", e.g. mangrove-canopy-2).
    """
    ckpt = getattr(model, "ckpt", None)
    if not isinstance(ckpt, dict):
        return None
    metrics = ckpt.get("train_metrics") or {}
    if not isinstance(metrics, dict) or "metrics/mAP50(M)" not in metrics:
        return None

    def metric(key: str) -> float | None:
        value = metrics.get(key)
        return round(float(value), 5) if isinstance(value, (int, float)) else None

    args = ckpt.get("train_args") or {}
    data = str(args.get("data") or "")
    dataset = Path(data.replace("\\", "/")).parent.name if data.endswith((".yaml", ".yml")) else None
    return {
        "map50_mask": metric("metrics/mAP50(M)"),
        "map50_95_mask": metric("metrics/mAP50-95(M)"),
        "precision_mask": metric("metrics/precision(M)"),
        "recall_mask": metric("metrics/recall(M)"),
        "dataset": dataset or None,
        "base_model": str(args.get("model")) if args.get("model") else None,
        "epochs": args.get("epochs"),
        "imgsz": args.get("imgsz"),
        "trained_at": str(ckpt.get("date")) if ckpt.get("date") else None,
    }


def class_names(model) -> list[str]:
    names = model.names
    if isinstance(names, dict):
        return [str(names[key]) for key in sorted(names)]
    return [str(name) for name in names]


def tile_starts(length: int, size: int, stride: int | None = None) -> list[int]:
    """Tile origins along one axis, `stride` apart (default: size, no overlap); the last one is shifted
    back so it ends at the edge."""
    if length <= size:
        return [0]
    starts = list(range(0, length - size, max(1, stride or size)))
    starts.append(length - size)
    return starts



# ---- Genus map --------------------------------------------------------------------------------
# The model runs once over every valid pixel of the raster and the result is kept as a "genus map":
# an RGB PNG on the raster's pixel grid with R = genus class + 1 (0 = nothing), G = the confidence of
# the most confident detection covering the pixel (0-255) and B = 255 where the raster has data.
# Any set of areas (the U-Net mask, or the owner's corrected polygons) is then counted from that map
# (summarize), so editing the areas never needs the model again (--recount).

# A detector runs the canopy model on a batch of size x size RGB tiles and returns, per tile,
# (masks (N, size, size) bool, class indexes (N,) int, confidences (N,) float32).
Detection = tuple[np.ndarray, np.ndarray, np.ndarray]


def _no_detections(size: int) -> Detection:
    return np.zeros((0, size, size), dtype=bool), np.zeros(0, dtype=int), np.zeros(0, dtype=np.float32)


def yolo_detector(model, size: int, conf_floor: float, device: str):
    """The local Ultralytics YOLO weights as a detector."""
    def detect(tiles: list[np.ndarray]) -> list[Detection]:
        bgr = [np.ascontiguousarray(tile[:, :, ::-1]) for tile in tiles]  # Ultralytics expects BGR arrays
        results = model.predict(bgr, imgsz=size, conf=conf_floor, retina_masks=True, device=device, verbose=False)
        out: list[Detection] = []
        for result in results:
            if result.masks is None or result.boxes is None or len(result.boxes) == 0:
                out.append(_no_detections(size))
                continue
            out.append((
                result.masks.data.cpu().numpy() > 0.5,
                result.boxes.cls.cpu().numpy().astype(int),
                result.boxes.conf.cpu().numpy().astype(np.float32),
            ))
        return out
    return detect


def roboflow_detector(names: list[str], size: int, conf_floor: float, api: dict, output: str | None, parallel: int):
    """The hosted Roboflow canopy Workflow (RF-DETR instance segmentation) as a detector.

    Each tile is one request (ml/predict_roboflow.py run_workflow: Bearer header, retries with backoff).
    The workflow answers per detection with `class` (a genus name), `confidence` and the mask as polygon
    `points` (or `rle_mask`); the mask is drawn at once and the points are dropped. Detections are mapped
    to `names` by class name, so the workflow's own class ids do not matter; unknown classes are skipped.
    """
    from concurrent.futures import ThreadPoolExecutor

    import predict_roboflow as rf

    index = {name.strip().lower(): i for i, name in enumerate(names)}

    def detect_one(tile: np.ndarray) -> Detection:
        segmentation = rf.find_segmentation(rf.run_workflow(rf.encode_jpeg(tile), **api), output or None)
        masks, labels, scores = [], [], []
        for prediction in segmentation.get("predictions", []):
            if not isinstance(prediction, dict):
                continue
            cls = index.get(str(prediction.get("class", "")).strip().lower())
            score = prediction.get("confidence")
            if cls is None or not isinstance(score, (int, float)) or score < conf_floor:
                continue
            mask = rf.prediction_mask(prediction, size, size)
            if mask is None or not mask.any():
                continue
            masks.append(mask)
            labels.append(cls)
            scores.append(float(score))
        if not masks:
            return _no_detections(size)
        return np.stack(masks), np.asarray(labels, dtype=int), np.asarray(scores, dtype=np.float32)

    def detect(tiles: list[np.ndarray]) -> list[Detection]:
        with ThreadPoolExecutor(max_workers=max(1, parallel)) as pool:
            return list(pool.map(detect_one, tiles))  # re-raises the first failed tile
    return detect


def label_raster(detect, rgb: np.ndarray, valid: np.ndarray, size: int,
                 batch_size: int, classes: int,
                 canopies: list | None = None, canopy_min_px: int = 0,
                 canopy_conf: float = 0.0, canopy_clip: np.ndarray | None = None,
                 canopy_overlap: float = 0.5, tile_overlap: int = 0) -> tuple[np.ndarray, np.ndarray, int]:
    """Label every valid pixel; with a `canopies` list also keep each detection as one canopy.

    A canopy entry is {cls, conf, pixels, center: (x, y) raster pixels, rings: [(N, 2) raster pixels]}.
    The canopy is clipped to canopy_clip (the mangrove extent; default: the valid pixels), so its outline
    follows the delineation. Only detections at >= canopy_conf count as a canopy, and canopies (or parts
    of one) smaller than canopy_min_px pixels are dropped (slivers along edges).

    Tiles overlap by tile_overlap pixels. A crown on a tile border is then seen by both tiles; the two
    views of the same genus are joined into one canopy (merge_canopy_views), so it is not cut straight at
    the border. In the genus map each pixel goes to the most confident detection of any tile.

    Duplicates: the model often detects one stand of trees twice with different genera (e.g. Sonneratia
    63% and Avicennia 24% on the same crowns). Canopies are taken most confident first, and one whose
    pixels are covered by already kept canopies for >= canopy_overlap of its area is dropped, whatever
    its genus. (The genus map is not affected: there each pixel already goes to the most confident one.)
    """
    clip = valid if canopy_clip is None else canopy_clip
    height, width = rgb.shape[:2]
    stride = size - max(0, min(int(tile_overlap), size // 2))
    windows: list[tuple[int, int]] = []
    seen: set[tuple[int, int]] = set()
    for y in tile_starts(height, size, stride):
        for x in tile_starts(width, size, stride):
            if (x, y) not in seen and valid[y:y + size, x:x + size].any():
                seen.add((x, y))
                windows.append((x, y))

    class_map = np.zeros((height, width), dtype=np.uint8)
    conf_map = np.zeros((height, width), dtype=np.uint8)
    views: list[dict] = []  # every confident detection of every tile, cropped to its bounding box

    for start in range(0, len(windows), max(1, batch_size)):
        batch = windows[start:start + batch_size]
        tiles = []
        for x, y in batch:
            tile = np.zeros((size, size, 3), dtype=np.uint8)
            patch = rgb[y:y + size, x:x + size]
            tile[: patch.shape[0], : patch.shape[1]] = patch
            tiles.append(tile)
        for tile_index, ((x, y), (masks, labels, scores)) in enumerate(zip(batch, detect(tiles)), start=start):
            h = min(size, height - y)
            w = min(size, width - x)
            tile_valid = valid[y:y + h, x:x + w]
            if len(scores) == 0:
                continue

            # Each pixel goes to the most confident detection covering it.
            best_conf = np.zeros((h, w), dtype=np.float32)
            best_class = np.full((h, w), -1, dtype=np.int16)
            for detection_mask, cls, score in zip(masks, labels, scores):
                if not 0 <= cls < classes:
                    continue
                crown = detection_mask[:h, :w]
                covered = crown & (score > best_conf)
                best_conf[covered] = score
                best_class[covered] = cls

            if canopies is not None:
                tile_clip = clip[y:y + h, x:x + w]
                for index in range(len(scores)):
                    cls, score = int(labels[index]), float(scores[index])
                    if not 0 <= cls < classes or score < canopy_conf:
                        continue
                    crown = masks[index][:h, :w] & tile_clip
                    rows = np.flatnonzero(crown.any(axis=1))
                    if rows.size == 0:
                        continue
                    cols = np.flatnonzero(crown.any(axis=0))
                    views.append({
                        "cls": cls, "conf": score, "tile": tile_index, "window": (x, y, w, h),
                        "x": x + int(cols[0]), "y": y + int(rows[0]),
                        "mask": crown[rows[0]:rows[-1] + 1, cols[0]:cols[-1] + 1].copy(),
                    })

            # Each pixel goes to the most confident detection of any tile covering it.
            level = np.clip(np.rint(best_conf * 255), 1, 255).astype(np.uint8)
            better = tile_valid & (best_class >= 0) & (level > conf_map[y:y + h, x:x + w])
            class_map[y:y + h, x:x + w][better] = (best_class[better] + 1).astype(np.uint8)
            conf_map[y:y + h, x:x + w][better] = level[better]

    if canopies is not None:
        canopies.extend(merge_canopy_views(views, height, width, canopy_min_px, canopy_overlap))

    return class_map, conf_map, len(windows)


def _view_box(view: dict) -> tuple[int, int, int, int]:
    """x0, y0, x1, y1 (exclusive) of a view's mask on the raster."""
    height, width = view["mask"].shape
    return view["x"], view["y"], view["x"] + width, view["y"] + height


def _same_crown(a: dict, b: dict, min_iou: float) -> bool:
    """Two views (from different tiles, same genus) of the same crown: inside the part of the raster both
    tiles saw, their masks largely agree."""
    ax, ay, aw, ah = a["window"]
    bx, by, bw, bh = b["window"]
    a0, a1, a2, a3 = _view_box(a)
    b0, b1, b2, b3 = _view_box(b)
    # Shared window, limited to where either mask has pixels.
    x0, y0 = max(ax, bx, min(a0, b0)), max(ay, by, min(a1, b1))
    x1, y1 = min(ax + aw, bx + bw, max(a2, b2)), min(ay + ah, by + bh, max(a3, b3))
    if x0 >= x1 or y0 >= y1:
        return False

    def crop(view: dict) -> np.ndarray:
        out = np.zeros((y1 - y0, x1 - x0), dtype=bool)
        vx0, vy0, vx1, vy1 = _view_box(view)
        sx0, sy0, sx1, sy1 = max(x0, vx0), max(y0, vy0), min(x1, vx1), min(y1, vy1)
        if sx0 < sx1 and sy0 < sy1:
            out[sy0 - y0:sy1 - y0, sx0 - x0:sx1 - x0] = view["mask"][sy0 - vy0:sy1 - vy0, sx0 - vx0:sx1 - vx0]
        return out

    first, second = crop(a), crop(b)
    union = int((first | second).sum())
    return union > 0 and int((first & second).sum()) >= min_iou * union


def merge_canopy_views(views: list[dict], height: int, width: int, min_px: int,
                       duplicate_overlap: float, min_iou: float = 0.3) -> list[dict]:
    """One canopy per crown from the detections of overlapping tiles.

    Views of the same genus from different tiles that agree where both tiles looked are joined (their
    masks united), so a crown on a tile border keeps its full outline. Then, most confident first, a
    canopy mostly covered by kept ones (>= duplicate_overlap, any genus) is a duplicate and dropped.
    (Letting weaker views complete a canopy was tried: it chains neighbouring stands into one blob.)
    """
    count = len(views)
    parent = list(range(count))

    def find(index: int) -> int:
        while parent[index] != index:
            parent[index] = parent[parent[index]]
            index = parent[index]
        return index

    if count:
        boxes = np.array([_view_box(view) for view in views], dtype=np.int64)
        cls = np.array([view["cls"] for view in views])
        tile = np.array([view["tile"] for view in views])
        for index in range(count):
            x0, y0, x1, y1 = boxes[index]
            others = np.flatnonzero(
                (np.arange(count) > index) & (cls == cls[index]) & (tile != tile[index])
                & (boxes[:, 0] < x1) & (boxes[:, 2] > x0) & (boxes[:, 1] < y1) & (boxes[:, 3] > y0)
            )
            for other in others:
                if find(index) != find(int(other)) and _same_crown(views[index], views[int(other)], min_iou):
                    parent[find(int(other))] = find(index)

    groups: dict[int, list[dict]] = {}
    for index, view in enumerate(views):
        groups.setdefault(find(index), []).append(view)

    merged = []
    for members in groups.values():
        x0 = min(view["x"] for view in members)
        y0 = min(view["y"] for view in members)
        x1 = max(_view_box(view)[2] for view in members)
        y1 = max(_view_box(view)[3] for view in members)
        mask = np.zeros((y1 - y0, x1 - x0), dtype=bool)
        for view in members:
            vh, vw = view["mask"].shape
            mask[view["y"] - y0:view["y"] - y0 + vh, view["x"] - x0:view["x"] - x0 + vw] |= view["mask"]
        merged.append({"cls": members[0]["cls"], "conf": max(view["conf"] for view in members),
                       "x": x0, "y": y0, "mask": mask})

    taken = np.zeros((height, width), dtype=bool)  # pixels of the canopies kept so far
    canopies = []
    for item in sorted(merged, key=lambda entry: entry["conf"], reverse=True):
        x0, y0, mask = item["x"], item["y"], item["mask"]
        mh, mw = mask.shape
        pixels = int(mask.sum())
        if pixels == 0 or (mask & taken[y0:y0 + mh, x0:x0 + mw]).sum() >= duplicate_overlap * pixels:
            continue  # mostly the same trees as a more confident canopy: a duplicate
        shape = crown_shape(mask, min_px)
        if shape is None:
            continue
        taken[y0:y0 + mh, x0:x0 + mw] |= mask
        offset = np.array([x0, y0], dtype=np.float32)
        center_col, center_row = shape["center"]
        canopies.append({
            "cls": item["cls"],
            "conf": item["conf"],
            "pixels": shape["pixels"],
            "center": (x0 + center_col, y0 + center_row),
            "rings": [ring + offset for ring in shape["rings"]],
        })
    return canopies


def crown_shape(crown: np.ndarray, min_px: int) -> dict | None:
    """Outline and pin position of one detected canopy, traced from its pixel mask.

    YOLO's own outline (masks.xy) joins every separate patch of a mask into one polygon, which draws
    straight lines across the image between the patches. Here each patch large enough (>= min_px) gets
    its own ring (outer edge only), and the pin goes to the point deepest inside the largest patch, so it
    always sits on the canopy. None when nothing large enough is left.
    """
    import cv2

    mask = crown.astype(np.uint8)
    count, components, stats, _ = cv2.connectedComponentsWithStats(mask, connectivity=8)
    minimum = max(1, int(min_px))
    parts = [label for label in range(1, count) if stats[label, cv2.CC_STAT_AREA] >= minimum]
    if not parts:
        return None

    rings = []
    pixels = 0
    for label in sorted(parts, key=lambda item: stats[item, cv2.CC_STAT_AREA], reverse=True):
        part = (components == label).astype(np.uint8)
        pixels += int(stats[label, cv2.CC_STAT_AREA])
        contours, _ = cv2.findContours(part, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
        if not contours:
            continue
        contour = max(contours, key=cv2.contourArea).reshape(-1, 2).astype(np.float32)
        if len(contour) >= 3:
            rings.append(contour + 0.5)  # pixel centres
    if not rings:
        return None

    largest = (components == max(parts, key=lambda item: stats[item, cv2.CC_STAT_AREA])).astype(np.uint8)
    depth = cv2.distanceTransform(np.pad(largest, 1), cv2.DIST_L2, 3)[1:-1, 1:-1]
    row, col = np.unravel_index(int(np.argmax(depth)), depth.shape)
    return {"pixels": pixels, "rings": rings, "center": (float(col) + 0.5, float(row) + 0.5)}


def close_mask(mask: np.ndarray, iterations: int) -> np.ndarray:
    """Morphological closing (3x3, 8-neighbour): joins canopies separated by thin gaps, like predict.py."""
    if iterations <= 0:
        return mask
    import cv2

    kernel = np.ones((3, 3), dtype=np.uint8)
    return cv2.morphologyEx(mask.astype(np.uint8), cv2.MORPH_CLOSE, kernel, iterations=int(iterations)).astype(bool)


CANOPY_OUTLINE_MAX_POINTS = 80
CANOPY_MAX_PARTS = 12  # separate patches drawn per canopy (largest first)


def canopy_features(canopies: list[dict], names: list[str], bounds: tuple[float, float, float, float],
                    width: int, height: int, pixel_m: float | None, limit: int) -> list[dict]:
    """The detected canopies as map features: largest first, at most `limit`.

    bounds: the raster's EPSG:3857 corners [ulx, uly, lrx, lry]. Each feature has the genus, the
    confidence, the ground area (m2, when pixel_m is known), its centre and a simplified outline,
    both as [lat, lng] like the extent features the editor draws.
    """
    import cv2
    import math

    ulx, uly, lrx, lry = bounds
    radius = 6378137.0

    def to_lat_lng(px: float, py: float) -> list[float]:
        mx = ulx + px / width * (lrx - ulx)
        my = uly - py / height * (uly - lry)
        lng = math.degrees(mx / radius)
        lat = math.degrees(2 * math.atan(math.exp(my / radius)) - math.pi / 2)
        return [round(lat, 7), round(lng, 7)]

    def simplify(ring: np.ndarray) -> np.ndarray:
        epsilon = 1.0
        while len(ring) > CANOPY_OUTLINE_MAX_POINTS and epsilon <= 64:
            simpler = cv2.approxPolyDP(ring.reshape(-1, 1, 2), epsilon, True).reshape(-1, 2)
            if len(simpler) < 3:
                break
            ring = simpler
            epsilon *= 1.6
        return ring

    features = []
    for canopy in sorted(canopies, key=lambda item: item["pixels"], reverse=True)[:max(0, limit)]:
        rings = []
        for ring in canopy["rings"][:CANOPY_MAX_PARTS]:
            ring = simplify(np.asarray(ring, dtype=np.float32))
            if len(ring) >= 3:
                rings.append([to_lat_lng(float(px), float(py)) for px, py in ring])
        features.append({
            "genus": names[canopy["cls"]],
            "confidence": round(canopy["conf"], 3),
            "area_m2": round(canopy["pixels"] * pixel_m * pixel_m, 2) if pixel_m else None,
            "center": to_lat_lng(*canopy["center"]),
            # Outline of each separate patch of the canopy (largest first); "coords" = the largest one.
            "rings": rings,
            "coords": rings[0] if rings else [],
        })
    return features


def drop_small_patches(labelled: np.ndarray, class_map: np.ndarray, classes: int, min_pixels: int) -> np.ndarray:
    """labelled without the patches of one genus smaller than min_pixels (8-connected).

    The model labels thin slivers along canopy/mud edges with random genera; real stands are larger.
    """
    if min_pixels <= 1:
        return labelled
    import cv2

    kept = np.zeros_like(labelled)
    for index in range(classes):
        genus = (labelled & (class_map == index + 1)).astype(np.uint8)
        if not genus.any():
            continue
        count, components, stats, _ = cv2.connectedComponentsWithStats(genus, connectivity=8)
        large = np.zeros(count, dtype=bool)
        large[1:] = stats[1:, cv2.CC_STAT_AREA] >= min_pixels
        kept |= large[components]
    return kept


def summarize(names: list[str], class_map: np.ndarray, conf_map: np.ndarray, target: np.ndarray,
              conf: float, prob_conf: float, min_patch_px: int = 0) -> dict:
    """Genus result for the pixels in target.

    genera: confident detections only (confidence >= conf), each genus's share of the labelled pixels.
    probabilities (experimental): every genus, its confidence-weighted share of the pixels labelled at
    >= prob_conf, so they sum to 1; equal shares (basis "none") when nothing was labelled.
    Patches of one genus smaller than min_patch_px inside the target are ignored (edge slivers).
    """
    confidence = conf_map.astype(np.float32) / 255.0
    target_pixels = int(target.sum())
    weak = target & (class_map > 0) & (confidence >= min(conf, prob_conf))
    weak = drop_small_patches(weak, class_map, len(names), min_patch_px)
    strict = weak & (confidence >= conf)

    labeled_pixels = int(strict.sum())
    genera = []
    for index, name in enumerate(names):
        selected = strict & (class_map == index + 1)
        pixels = int(selected.sum())
        if pixels == 0:
            continue
        genera.append({
            "genus": name,
            "pixels": pixels,
            "share_percent": round(pixels / labeled_pixels * 100.0, 2),
            "coverage_percent": round(pixels / target_pixels * 100.0, 2) if target_pixels else 0.0,
            "mean_confidence": round(float(confidence[selected].mean()), 4),
        })
    genera.sort(key=lambda item: item["pixels"], reverse=True)

    weights = [float(confidence[weak & (class_map == index + 1)].sum()) for index in range(len(names))]
    total = sum(weights)
    probabilities = []
    for index, name in enumerate(names):
        selected = weak & (class_map == index + 1)
        pixels = int(selected.sum())
        probabilities.append({
            "genus": name,
            "probability": round(weights[index] / total, 4) if total > 0 else round(1 / len(names), 4),
            "pixels": pixels,
            "mean_confidence": round(weights[index] / pixels, 4) if pixels else 0.0,
        })
    probabilities.sort(key=lambda item: item["probability"], reverse=True)

    return {
        "ok": True,
        "classes": names,
        "genera": genera,
        "pixels_target": target_pixels,
        "pixels_labeled": labeled_pixels,
        "labeled_percent": round(labeled_pixels / target_pixels * 100.0, 2) if target_pixels else 0.0,
        "probabilities": probabilities,
        "probability_basis": "pixels" if total > 0 else "none",
        "probability_threshold": min(conf, prob_conf),
        "confidence_threshold": conf,
        "min_patch_px": int(min_patch_px),
    }


def save_label_map(path: Path, class_map: np.ndarray, conf_map: np.ndarray, valid: np.ndarray) -> None:
    Image.fromarray(np.dstack([class_map, conf_map, valid.astype(np.uint8) * 255])).save(path, optimize=True)


def load_label_map(path: Path) -> tuple[np.ndarray, np.ndarray, np.ndarray]:
    Image.MAX_IMAGE_PIXELS = None
    data = np.asarray(Image.open(path).convert("RGB"))
    return data[:, :, 0], data[:, :, 1], data[:, :, 2] > 0


def predict_genera(
    raster_path: Path,
    weights_path: Path | None,
    mask_path: Path | None,
    size: int,
    conf: float,
    batch_size: int,
    prob_conf: float = 0.01,
    labels_out: Path | None = None,
    min_patch_px: int = 0,
    extent_out: Path | None = None,
    extent_conf: float = 0.25,
    close_iterations: int = 0,
    canopies_out: Path | None = None,
    bounds: tuple[float, float, float, float] | None = None,
    pixel_m: float | None = None,
    canopy_min_px: int = 0,
    canopy_max: int = 3000,
    canopy_conf: float | None = None,
    canopy_overlap: float = 0.5,
    areas_path: Path | None = None,
    tile_overlap: int = 0,
    roboflow: dict | None = None,
) -> dict:
    """Run the canopy model over the raster: the local YOLO weights, or with `roboflow` (settings from
    main(): names, api, output, parallel) the hosted Roboflow canopy Workflow."""
    Image.MAX_IMAGE_PIXELS = None
    conf_floor = min(conf, prob_conf)
    if roboflow is not None:
        names = list(roboflow["names"])
        device = "roboflow"
        detect = roboflow_detector(names, size, conf_floor, roboflow["api"], roboflow.get("output"), roboflow["parallel"])
        batch_size = max(batch_size, roboflow["parallel"])
    else:
        import torch

        model = load_model(weights_path)
        names = class_names(model)
        device = "cuda" if torch.cuda.is_available() else "cpu"
        detect = yolo_detector(model, size, conf_floor, device)

    source = Image.open(raster_path)
    source.load()
    if source.mode == "RGBA":
        rgba = np.asarray(source)
        rgb = np.ascontiguousarray(rgba[:, :, :3])
        valid = rgba[:, :, 3] > 0
    else:
        rgb = np.asarray(source.convert("RGB"))
        valid = np.ones(rgb.shape[:2], dtype=bool)
    source.close()

    target = valid.copy()
    if mask_path is not None:
        mask = np.asarray(Image.open(mask_path).convert("L")) > 0
        if mask.shape != target.shape:
            raise RuntimeError(f"Mask size {mask.shape[::-1]} does not match raster size {target.shape[::-1]}.")
        target &= mask
    elif areas_path is not None:
        # The current mangrove areas (the AI delineation, or the owner's corrections), drawn onto this raster.
        areas = json.loads(Path(areas_path).read_text(encoding="utf-8"))
        if not isinstance(areas.get("raster"), dict):
            raise RuntimeError("The areas file needs the raster position (raster.bounds, latitude, offset).")
        target &= draw_areas(areas, areas["raster"], rgb.shape[1], rgb.shape[0])
    has_target = mask_path is not None or areas_path is not None

    canopies: list[dict] | None = [] if canopies_out is not None else None
    if canopies_out is not None and bounds is None:
        raise RuntimeError("--canopies-out needs --bounds.")

    # Only the mangrove pixels need the model, unless the whole raster is wanted: for a genus map to
    # keep, for the model's own extent, or to pinpoint every canopy.
    label_area = valid if (labels_out is not None or extent_out is not None or canopies is not None) else target
    class_map, conf_map, tiles = label_raster(
        detect, rgb, label_area, size, batch_size, len(names),
        canopies, canopy_min_px, conf if canopy_conf is None else canopy_conf,
        target if has_target else None, canopy_overlap, tile_overlap,
    )
    if labels_out is not None:
        save_label_map(labels_out, class_map, conf_map, valid)

    extent_stats: dict = {}
    if extent_out is not None:
        # The mangrove extent according to this model: every canopy found at >= extent_conf.
        extent = valid & (class_map > 0) & (conf_map >= max(1, int(round(extent_conf * 255))))
        extent = close_mask(extent, close_iterations) & valid
        extent_out.parent.mkdir(parents=True, exist_ok=True)
        Image.fromarray(extent.astype(np.uint8), mode="L").save(extent_out, format="PNG", optimize=True)
        if not has_target:
            target = extent
        valid_pixels = int(valid.sum())
        extent_pixels = int(extent.sum())
        extent_stats = {
            "extent_mask": str(extent_out),
            "extent_confidence": extent_conf,
            "pixels_mangrove": extent_pixels,
            "pixels_valid": valid_pixels,
            "mangrove_coverage_percent": round(extent_pixels / valid_pixels * 100.0, 2) if valid_pixels else 0.0,
            "mean_confidence": round(float(conf_map[extent].mean()) / 255.0, 4) if extent_pixels else 0.0,
        }

    result = summarize(names, class_map, conf_map, target, conf, prob_conf, min_patch_px)
    result.update(extent_stats)
    result.update({
        "tiles": tiles,
        "input_size": size,
        "device": device,
        "label_map": str(labels_out) if labels_out is not None else None,
        "label_map_size": [int(rgb.shape[1]), int(rgb.shape[0])],
    })

    if canopies is not None and canopies_out is not None:
        # Only the canopies inside the mangrove extent (the U-Net mask or areas, or this model's own extent):
        # a crown the delineation does not cover is not pinpointed.
        canopies = [
            canopy for canopy in canopies
            if target[min(rgb.shape[0] - 1, int(canopy["center"][1])), min(rgb.shape[1] - 1, int(canopy["center"][0]))]
        ]
        features = canopy_features(canopies, names, bounds, rgb.shape[1], rgb.shape[0], pixel_m, canopy_max)
        counts = {name: 0 for name in names}
        for canopy in canopies:
            counts[names[canopy["cls"]]] += 1
        canopies_out.parent.mkdir(parents=True, exist_ok=True)
        canopies_out.write_text(json.dumps({
            "count": len(canopies),
            "kept": len(features),
            "by_genus": counts,
            "canopies": features,
        }, separators=(",", ":")), encoding="utf-8")
        result.update({"canopies": str(canopies_out), "canopy_count": len(canopies), "canopies_kept": len(features),
                       "canopies_by_genus": counts})
    return result


def draw_areas(areas: dict, raster: dict, width: int, height: int) -> np.ndarray:
    """Mangrove areas drawn onto a raster's pixel grid (True inside).

    areas: {"rings": [[[lng, lat], ...], ...], "offset": {east_m, north_m}} as saved by the editor; the rings
    sit on the imagery at that offset.
    raster: {"bounds": [ulx, uly, lrx, lry] (EPSG:3857), "latitude", "offset": {east_m, north_m}}: where the
    raster sits and the offset already contained in it. The rings are moved back by the difference first.
    """
    import math

    ulx, uly, lrx, lry = (float(value) for value in raster["bounds"])
    # Ground metres to Web Mercator units at the survey latitude.
    scale = 1 / max(0.01, math.cos(math.radians(float(raster.get("latitude", 0.0)))))
    saved_offset = areas.get("offset") or {}
    raster_offset = raster.get("offset") or {}
    shift_x = (float(saved_offset.get("east_m", 0)) - float(raster_offset.get("east_m", 0))) * scale
    shift_y = (float(saved_offset.get("north_m", 0)) - float(raster_offset.get("north_m", 0))) * scale

    radius = 6378137.0
    drawn = Image.new("L", (width, height), 0)
    pen = ImageDraw.Draw(drawn)
    for ring in areas.get("rings", []):
        points = []
        for lng, lat in ring:
            x = radius * math.radians(float(lng)) - shift_x
            y = radius * math.log(math.tan(math.pi / 4 + math.radians(float(lat)) / 2)) - shift_y
            points.append(((x - ulx) / (lrx - ulx) * width, (uly - y) / (uly - lry) * height))
        if len(points) >= 3:
            pen.polygon(points, fill=255)
    return np.asarray(drawn) > 0


def recount(labels_path: Path, meta_path: Path, areas_path: Path, min_patch_m2: float | None = None) -> dict:
    """Genus result for the owner's corrected areas, counted from a saved genus map (no model run).

    meta: {"classes", "bounds": [ulx, uly, lrx, lry] (EPSG:3857), "latitude", "offset": {east_m, north_m},
           "conf", "prob_conf"} written when the map was made.
    areas: {"rings": [[[lng, lat], ...], ...], "offset": {east_m, north_m}} as saved by the editor (see draw_areas).
    """
    import math

    meta = json.loads(Path(meta_path).read_text(encoding="utf-8"))
    areas = json.loads(Path(areas_path).read_text(encoding="utf-8"))
    class_map, conf_map, valid = load_label_map(labels_path)
    height, width = class_map.shape
    ulx, uly, lrx, lry = (float(value) for value in meta["bounds"])
    scale = 1 / max(0.01, math.cos(math.radians(float(meta.get("latitude", 0.0)))))

    target = draw_areas(areas, meta, width, height) & valid
    # Smallest patch that counts: from the current setting (ground m²) when given, so maps made before the
    # setting existed are filtered too; else what was used when the map was made.
    if min_patch_m2 is not None:
        pixel_m = abs(lrx - ulx) / width / scale  # ground metres per pixel
        min_patch_px = math.ceil(max(0.0, min_patch_m2) / max(pixel_m * pixel_m, 1e-9))
    else:
        min_patch_px = int(meta.get("min_patch_px", 0))
    return summarize(
        [str(name) for name in meta["classes"]], class_map, conf_map, target,
        float(meta.get("conf", 0.1)), float(meta.get("prob_conf", 0.01)), min_patch_px,
    )


def main() -> int:
    parser = argparse.ArgumentParser(description="Mangrove canopy detection, extent and genus suggestion (YOLO segmentation)")
    mode = parser.add_mutually_exclusive_group(required=True)
    mode.add_argument("--raster", help="RGB/RGBA raster to classify tile by tile")
    mode.add_argument("--validate-weights", action="store_true")
    mode.add_argument("--recount", metavar="AREAS_JSON", help="Count the genus inside edited areas from a saved genus map")
    parser.add_argument("--weights", help="YOLO weights (.pt); needed for --raster and --validate-weights")
    parser.add_argument("--mask", default=None, help="U-Net mangrove mask PNG (same size as the raster)")
    parser.add_argument("--areas", default=None,
                        help="Count inside these mangrove areas instead of a mask (JSON: rings, offset, raster position)")
    parser.add_argument("--size", type=int, default=640)
    parser.add_argument("--overlap", type=int, default=0,
                        help="Pixels neighbouring tiles overlap (--raster); crowns on tile borders are then joined")
    parser.add_argument("--conf", type=float, default=0.1)
    parser.add_argument("--batch", type=int, default=4)
    parser.add_argument("--prob-conf", type=float, default=0.01,
                        help="Lower threshold for the experimental per-genus probabilities")
    parser.add_argument("--labels-out", default=None, help="Write the genus map PNG here (--raster)")
    parser.add_argument("--min-patch-px", type=int, default=0,
                        help="Ignore patches of one genus smaller than this many pixels (--raster)")
    parser.add_argument("--min-patch-m2", type=float, default=None,
                        help="Ignore patches smaller than this ground area (--recount; default: the value in --meta)")
    parser.add_argument("--labels", default=None, help="Genus map PNG (--recount)")
    parser.add_argument("--meta", default=None, help="Genus map metadata JSON (--recount)")
    parser.add_argument("--extent-out", default=None,
                        help="Write the model's own mangrove extent (union of canopies) as a 0/1 mask PNG (--raster)")
    parser.add_argument("--extent-conf", type=float, default=0.25, help="Canopies below this confidence are left out of the extent")
    parser.add_argument("--close", type=int, default=0, help="Extent mask closing iterations (like predict.py --close)")
    parser.add_argument("--canopies-out", default=None, help="Write every detected canopy as JSON here (--raster; needs --bounds)")
    parser.add_argument("--bounds", type=float, nargs=4, default=None, metavar=("ULX", "ULY", "LRX", "LRY"),
                        help="EPSG:3857 corners of the raster, to place the canopies on the map")
    parser.add_argument("--pixel-m", type=float, default=None, help="Ground metres per pixel, for the canopy areas")
    parser.add_argument("--canopy-min-px", type=int, default=0, help="Ignore canopies smaller than this many pixels")
    parser.add_argument("--canopy-conf", type=float, default=None,
                        help="Only detections at >= this confidence are pinpointed as canopies (default: --conf)")
    parser.add_argument("--canopy-overlap", type=float, default=0.5,
                        help="Drop a canopy when this share of it is covered by more confident canopies (duplicates)")
    parser.add_argument("--canopy-max", type=int, default=3000, help="Keep at most this many canopies (largest first)")
    # The hosted Roboflow canopy Workflow instead of --weights (--raster only). The API key is read from the
    # ROBOFLOW_API_KEY environment variable only and sent as an Authorization: Bearer header.
    parser.add_argument("--roboflow", action="store_true", help="Use the Roboflow canopy Workflow instead of --weights")
    parser.add_argument("--rf-api-url", default=os.environ.get("ROBOFLOW_API_URL") or "https://serverless.roboflow.com")
    parser.add_argument("--rf-workspace", default=os.environ.get("ROBOFLOW_WORKSPACE") or "denje-s-works")
    parser.add_argument("--rf-workflow", default=os.environ.get("ROBOFLOW_GENUS_WORKFLOW") or DEFAULT_CANOPY_WORKFLOW)
    parser.add_argument("--rf-output", default="", help="Workflow output name (default: auto-detect)")
    parser.add_argument("--rf-classes", default=DEFAULT_CANOPY_CLASSES,
                        help="Comma-separated genus classes of the workflow, in the order the genus map stores them")
    parser.add_argument("--rf-parallel", type=int, default=4, help="Tiles sent to Roboflow at once")
    parser.add_argument("--rf-request-timeout", type=float, default=60.0)
    parser.add_argument("--rf-retries", type=int, default=3)
    args = parser.parse_args()

    roboflow = None
    if args.roboflow:
        roboflow = {
            "names": [c.strip() for c in args.rf_classes.split(",") if c.strip()],
            "output": args.rf_output,
            "parallel": max(1, args.rf_parallel),
            "api": {
                "api_key": os.environ.get("ROBOFLOW_API_KEY", "").strip(),
                "workspace": args.rf_workspace,
                "workflow_id": args.rf_workflow,
                "api_url": args.rf_api_url,
                "timeout": args.rf_request_timeout,
                "retries": max(0, args.rf_retries),
            },
        }

    try:
        if args.recount:
            if not args.labels or not args.meta:
                raise RuntimeError("--recount needs --labels and --meta.")
            result = recount(Path(args.labels), Path(args.meta), Path(args.recount), args.min_patch_m2)
        elif roboflow is not None and not args.raster:
            raise RuntimeError("--roboflow works with --raster only.")
        elif roboflow is None and not args.weights:
            raise RuntimeError("--weights is required.")
        elif args.validate_weights:
            model = load_model(Path(args.weights))
            result = {"ok": True, "task": model.task, "classes": class_names(model), "training": training_summary(model)}
        else:
            result = predict_genera(
                Path(args.raster),
                Path(args.weights) if args.weights else None,
                Path(args.mask) if args.mask else None,
                args.size,
                args.conf,
                args.batch,
                args.prob_conf,
                Path(args.labels_out) if args.labels_out else None,
                args.min_patch_px,
                Path(args.extent_out) if args.extent_out else None,
                args.extent_conf,
                args.close,
                Path(args.canopies_out) if args.canopies_out else None,
                tuple(args.bounds) if args.bounds else None,
                args.pixel_m,
                args.canopy_min_px,
                args.canopy_max,
                args.canopy_conf,
                args.canopy_overlap,
                Path(args.areas) if args.areas else None,
                args.overlap,
                roboflow,
            )
    except Exception as exc:
        print(json.dumps({"ok": False, "error": str(exc), "error_type": type(exc).__name__}), flush=True)
        return 1

    print(json.dumps(result), flush=True)
    return 0


if __name__ == "__main__":
    sys.exit(main())
