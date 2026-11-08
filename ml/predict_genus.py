#!/usr/bin/env python3
"""Suggest the mangrove genera on a drone raster with an Ultralytics YOLO segmentation model.

Only pixels inside the U-Net mangrove mask (predict.py --raster) are counted, so the
result says which genera the detected mangrove areas consist of. With --labels-out the
per-pixel genus map is also saved, so --recount can count the genus inside the owner's
edited areas later without running the model. Prints JSON as the last stdout line, like
predict.py.
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


def tile_starts(length: int, size: int) -> list[int]:
    if length <= size:
        return [0]
    starts = list(range(0, length - size, size))
    starts.append(length - size)
    return starts



# ---- Genus map --------------------------------------------------------------------------------
# The model runs once over every valid pixel of the raster and the result is kept as a "genus map":
# an RGB PNG on the raster's pixel grid with R = genus class + 1 (0 = nothing), G = the confidence of
# the most confident detection covering the pixel (0-255) and B = 255 where the raster has data.
# Any set of areas (the U-Net mask, or the owner's corrected polygons) is then counted from that map
# (summarize), so editing the areas never needs the model again (--recount).

def label_raster(model, rgb: np.ndarray, valid: np.ndarray, size: int, conf_floor: float,
                 batch_size: int, device: str, classes: int) -> tuple[np.ndarray, np.ndarray, int]:
    height, width = rgb.shape[:2]
    # Non-overlapping tiles (the last row/column shifted back to fit), so each pixel is labelled once.
    windows: list[tuple[int, int]] = []
    seen: set[tuple[int, int]] = set()
    for y in tile_starts(height, size):
        for x in tile_starts(width, size):
            if (x, y) not in seen and valid[y:y + size, x:x + size].any():
                seen.add((x, y))
                windows.append((x, y))

    class_map = np.zeros((height, width), dtype=np.uint8)
    conf_map = np.zeros((height, width), dtype=np.uint8)
    counted = np.zeros((height, width), dtype=bool)  # shifted edge tiles overlap their neighbours

    for start in range(0, len(windows), max(1, batch_size)):
        batch = windows[start:start + batch_size]
        tiles = []
        for x, y in batch:
            tile = np.zeros((size, size, 3), dtype=np.uint8)
            patch = rgb[y:y + size, x:x + size]
            tile[: patch.shape[0], : patch.shape[1]] = patch
            tiles.append(np.ascontiguousarray(tile[:, :, ::-1]))  # Ultralytics expects BGR arrays
        results = model.predict(tiles, imgsz=size, conf=conf_floor, retina_masks=True, device=device, verbose=False)
        for (x, y), result in zip(batch, results):
            h = min(size, height - y)
            w = min(size, width - x)
            region = valid[y:y + h, x:x + w] & ~counted[y:y + h, x:x + w]
            counted[y:y + h, x:x + w] |= valid[y:y + h, x:x + w]
            if result.masks is None or result.boxes is None or len(result.boxes) == 0:
                continue

            masks = result.masks.data.cpu().numpy() > 0.5
            labels = result.boxes.cls.cpu().numpy().astype(int)
            scores = result.boxes.conf.cpu().numpy().astype(np.float32)
            # Each pixel goes to the most confident detection covering it.
            best_conf = np.zeros((h, w), dtype=np.float32)
            best_class = np.full((h, w), -1, dtype=np.int16)
            for detection_mask, cls, score in zip(masks, labels, scores):
                if not 0 <= cls < classes:
                    continue
                covered = detection_mask[:h, :w] & (score > best_conf)
                best_conf[covered] = score
                best_class[covered] = cls

            selected = region & (best_class >= 0)
            class_map[y:y + h, x:x + w][selected] = (best_class[selected] + 1).astype(np.uint8)
            conf_map[y:y + h, x:x + w][selected] = np.clip(np.rint(best_conf[selected] * 255), 1, 255).astype(np.uint8)

    return class_map, conf_map, len(windows)


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
    weights_path: Path,
    mask_path: Path | None,
    size: int,
    conf: float,
    batch_size: int,
    prob_conf: float = 0.01,
    labels_out: Path | None = None,
    min_patch_px: int = 0,
) -> dict:
    import torch

    Image.MAX_IMAGE_PIXELS = None
    model = load_model(weights_path)
    names = class_names(model)
    device = "cuda" if torch.cuda.is_available() else "cpu"

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

    # Without a genus map to keep, only the mangrove pixels need the model.
    label_area = valid if labels_out is not None else target
    class_map, conf_map, tiles = label_raster(
        model, rgb, label_area, size, min(conf, prob_conf), batch_size, device, len(names)
    )
    if labels_out is not None:
        save_label_map(labels_out, class_map, conf_map, valid)

    result = summarize(names, class_map, conf_map, target, conf, prob_conf, min_patch_px)
    result.update({
        "tiles": tiles,
        "input_size": size,
        "device": device,
        "label_map": str(labels_out) if labels_out is not None else None,
        "label_map_size": [int(rgb.shape[1]), int(rgb.shape[0])],
    })
    return result


def recount(labels_path: Path, meta_path: Path, areas_path: Path, min_patch_m2: float | None = None) -> dict:
    """Genus result for the owner's corrected areas, counted from a saved genus map (no model run).

    meta: {"classes", "bounds": [ulx, uly, lrx, lry] (EPSG:3857), "latitude", "offset": {east_m, north_m},
           "conf", "prob_conf"} written when the map was made.
    areas: {"rings": [[[lng, lat], ...], ...], "offset": {east_m, north_m}} as saved by the editor. The rings
    sit on the imagery at the saved offset; the map was made at meta.offset, so they are moved back by the
    difference first.
    """
    import math

    meta = json.loads(Path(meta_path).read_text(encoding="utf-8"))
    areas = json.loads(Path(areas_path).read_text(encoding="utf-8"))
    class_map, conf_map, valid = load_label_map(labels_path)
    height, width = class_map.shape
    ulx, uly, lrx, lry = (float(value) for value in meta["bounds"])

    # Ground metres to Web Mercator units at the survey latitude.
    scale = 1 / max(0.01, math.cos(math.radians(float(meta.get("latitude", 0.0)))))
    saved_offset = areas.get("offset") or {}
    map_offset = meta.get("offset") or {}
    shift_x = (float(saved_offset.get("east_m", 0)) - float(map_offset.get("east_m", 0))) * scale
    shift_y = (float(saved_offset.get("north_m", 0)) - float(map_offset.get("north_m", 0))) * scale

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

    target = (np.asarray(drawn) > 0) & valid
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
    parser = argparse.ArgumentParser(description="Mangrove genus suggestion (YOLO segmentation)")
    mode = parser.add_mutually_exclusive_group(required=True)
    mode.add_argument("--raster", help="RGB/RGBA raster to classify tile by tile")
    mode.add_argument("--validate-weights", action="store_true")
    mode.add_argument("--recount", metavar="AREAS_JSON", help="Count the genus inside edited areas from a saved genus map")
    parser.add_argument("--weights", help="YOLO weights (.pt); needed for --raster and --validate-weights")
    parser.add_argument("--mask", default=None, help="U-Net mangrove mask PNG (same size as the raster)")
    parser.add_argument("--size", type=int, default=640)
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
    args = parser.parse_args()

    try:
        if args.recount:
            if not args.labels or not args.meta:
                raise RuntimeError("--recount needs --labels and --meta.")
            result = recount(Path(args.labels), Path(args.meta), Path(args.recount), args.min_patch_m2)
        elif not args.weights:
            raise RuntimeError("--weights is required.")
        elif args.validate_weights:
            model = load_model(Path(args.weights))
            result = {"ok": True, "task": model.task, "classes": class_names(model), "training": training_summary(model)}
        else:
            result = predict_genera(
                Path(args.raster),
                Path(args.weights),
                Path(args.mask) if args.mask else None,
                args.size,
                args.conf,
                args.batch,
                args.prob_conf,
                Path(args.labels_out) if args.labels_out else None,
                args.min_patch_px,
            )
    except Exception as exc:
        print(json.dumps({"ok": False, "error": str(exc)}), flush=True)
        return 1

    print(json.dumps(result), flush=True)
    return 0


if __name__ == "__main__":
    sys.exit(main())
