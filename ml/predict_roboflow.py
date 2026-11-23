#!/usr/bin/env python3
"""Mangrove extent from the hosted Roboflow Workflow (RF-DETR semantic segmentation).

Used when ML_DRONE_EXTENT_MODEL=roboflow (MangroveSegmentationService::predictRoboflowExtent).
The workflow takes one input, `image`, and returns one output (default name `predictions`):

    {"image": {"width": W, "height": H},
     "predictions": [{"class": "mangrove-area", "class_id": 1, "confidence": 0.96,
                      "rle_mask": {"size": [H, W], "counts": "<COCO compressed RLE>"}, ...}]}

Modes (JSON result on the last stdout line, like predict.py):
  --raster R --mask M   cut the raster into tiles, run each through the workflow, write a 0/1 mask PNG
                        on the raster's pixel grid (what SegmentDroneImagery / MangroveAreaDetector expect)
  --image I [--mask M]  one image in one request (smoke test); also lists the workflow's output keys

The API key comes from the ROBOFLOW_API_KEY environment variable only and is sent as an
`Authorization: Bearer` header, never on the command line, in the URL or in the body.
"""

from __future__ import annotations

import argparse
import base64
import io
import json
import os
import random
import sys
import threading
import time
from concurrent.futures import ThreadPoolExecutor
from pathlib import Path

import numpy as np
import requests
from PIL import Image, ImageDraw

DEFAULT_API_URL = "https://serverless.roboflow.com"
DEFAULT_WORKSPACE = "denje-s-works"
DEFAULT_WORKFLOW = (
    "mangrove-area-delineation-3-rfdetr-sem-small-t1-vmangrove-area-delineation-3-rfdetr-sem-small-t1-logic"
)


class RoboflowError(RuntimeError):
    """Base class for every Roboflow failure."""


class RoboflowConfigError(RoboflowError):
    """Missing API key or settings."""


class RoboflowAuthError(RoboflowError):
    """The API key was refused (401/403)."""


class RoboflowRequestError(RoboflowError):
    """The request failed (network, timeout, 4xx/5xx), after the retries."""


class RoboflowResponseError(RoboflowError):
    """The workflow answered, but not in the expected shape."""


def _is_empty_result_error(status: int, text: str) -> bool:
    """The hosted workflow answers HTTP 500 instead of an empty list when the model finds nothing
    (it cannot serialize an empty supervision Detections). Seen on water, mud and blank tiles."""
    return status == 500 and "output serialization" in text and "Detections" in text


_thread_state = threading.local()


def _session() -> requests.Session:
    session = getattr(_thread_state, "session", None)
    if session is None:
        session = requests.Session()
        _thread_state.session = session
    return session


def run_workflow(
    image_bytes: bytes,
    *,
    api_key: str,
    workspace: str = DEFAULT_WORKSPACE,
    workflow_id: str = DEFAULT_WORKFLOW,
    api_url: str = DEFAULT_API_URL,
    timeout: float = 60.0,
    retries: int = 3,
    backoff: float = 2.0,
) -> dict:
    """Run the workflow on one encoded image (JPEG/PNG bytes).

    Returns the first entry of `outputs`: a dict keyed by the workflow's own output names.
    Retries connection errors, timeouts, 429 and 5xx with exponential backoff.
    """
    if not api_key:
        raise RoboflowConfigError("ROBOFLOW_API_KEY is not set.")

    url = f"{api_url.rstrip('/')}/{workspace}/workflows/{workflow_id}"
    body = {"inputs": {"image": {"type": "base64", "value": base64.b64encode(image_bytes).decode("ascii")}}}
    headers = {"Authorization": f"Bearer {api_key}", "Content-Type": "application/json"}

    last_error = "no attempt made"
    for attempt in range(max(0, retries) + 1):
        if attempt:
            time.sleep(backoff * (2 ** (attempt - 1)) + random.uniform(0, 0.5))
        try:
            response = _session().post(url, json=body, headers=headers, timeout=timeout)
        except (requests.ConnectionError, requests.Timeout) as exc:
            last_error = f"{type(exc).__name__}: {exc}"
            continue

        status = response.status_code
        if status in (401, 403):
            raise RoboflowAuthError(f"Roboflow refused the API key (HTTP {status}).")
        if _is_empty_result_error(status, response.text):
            # Nothing found: the same shape as a normal answer with no predictions.
            return {"predictions": {"predictions": [], "empty_result": True}}
        if status == 429 or status >= 500:
            last_error = f"HTTP {status}: {response.text[:200]}"
            continue
        if status >= 400:
            raise RoboflowRequestError(f"Roboflow workflow request failed (HTTP {status}): {response.text[:300]}")

        try:
            data = response.json()
        except ValueError as exc:
            raise RoboflowResponseError("Roboflow returned a response that is not JSON.") from exc
        outputs = data.get("outputs") if isinstance(data, dict) else None
        if not isinstance(outputs, list) or not outputs or not isinstance(outputs[0], dict):
            raise RoboflowResponseError("Roboflow response has no outputs.")
        return outputs[0]

    raise RoboflowRequestError(f"Roboflow workflow request failed after {retries + 1} attempts ({last_error}).")


def find_segmentation(output: dict, output_name: str | None = None) -> dict:
    """The segmentation result inside one workflow output: a dict with a `predictions` list.

    With output_name, that output must be it; otherwise the first output that looks like one is used,
    so renaming the output in the workflow editor does not break this.
    """
    if output_name:
        value = output.get(output_name)
        if not isinstance(value, dict) or not isinstance(value.get("predictions"), list):
            raise RoboflowResponseError(
                f"Workflow output '{output_name}' is missing or not a segmentation result "
                f"(outputs: {', '.join(sorted(output)) or 'none'})."
            )
        return value

    for value in output.values():
        if isinstance(value, dict) and isinstance(value.get("predictions"), list):
            return value
    raise RoboflowResponseError(
        f"No segmentation result in the workflow outputs ({', '.join(sorted(output)) or 'none'})."
    )


def _rle_counts(counts: str) -> list[int]:
    """Decode a COCO compressed RLE string (pycocotools' rleFrString) into run lengths."""
    runs: list[int] = []
    position = 0
    while position < len(counts):
        value = 0
        shift = 0
        more = True
        while more:
            if position >= len(counts):
                raise RoboflowResponseError("Truncated RLE mask in the Roboflow response.")
            char = ord(counts[position]) - 48
            value |= (char & 0x1F) << (5 * shift)
            more = bool(char & 0x20)
            position += 1
            shift += 1
            if not more and (char & 0x10):
                value |= -1 << (5 * shift)
        if len(runs) > 2:
            value += runs[-2]
        runs.append(value)
    return runs


def decode_rle(rle: dict) -> np.ndarray:
    """COCO RLE ({"size": [h, w], "counts": str | list}) to a boolean (h, w) mask."""
    size = rle.get("size")
    counts = rle.get("counts")
    if not isinstance(size, list) or len(size) != 2:
        raise RoboflowResponseError("RLE mask without a size.")
    height, width = int(size[0]), int(size[1])
    runs = _rle_counts(counts) if isinstance(counts, str) else [int(c) for c in (counts or [])]

    flat = np.zeros(height * width, dtype=bool)
    position = 0
    for index, run in enumerate(runs):
        if run < 0:
            raise RoboflowResponseError("Invalid RLE mask in the Roboflow response.")
        if index % 2:
            flat[position:position + run] = True
        position += run
    if position != height * width:
        raise RoboflowResponseError(f"RLE mask covers {position} pixels, expected {height * width}.")
    # COCO masks are stored column by column.
    return flat.reshape((height, width), order="F")


def prediction_mask(prediction: dict, height: int, width: int) -> np.ndarray | None:
    """One prediction's mask at (height, width): from rle_mask, else from polygon points."""
    rle = prediction.get("rle_mask")
    if isinstance(rle, dict):
        mask = decode_rle(rle)
        if mask.shape != (height, width):
            mask = np.asarray(
                Image.fromarray(mask.astype(np.uint8) * 255).resize((width, height), Image.NEAREST)
            ) > 0
        return mask

    points = prediction.get("points")
    if isinstance(points, list) and len(points) >= 3:
        canvas = Image.new("L", (width, height), 0)
        ImageDraw.Draw(canvas).polygon([(float(p["x"]), float(p["y"])) for p in points], fill=1)
        return np.asarray(canvas) > 0
    return None


def mangrove_mask(segmentation: dict, height: int, width: int, classes: set[str]) -> tuple[np.ndarray, list[float]]:
    """Union of the predictions whose class is a mangrove class (any non-background class when empty)."""
    mask = np.zeros((height, width), dtype=bool)
    confidences: list[float] = []
    for prediction in segmentation.get("predictions", []):
        if not isinstance(prediction, dict):
            continue
        name = str(prediction.get("class", "")).strip().lower()
        if classes and name not in classes:
            continue
        if not classes and name in ("", "background"):
            continue
        part = prediction_mask(prediction, height, width)
        if part is None:
            continue
        mask |= part
        if isinstance(prediction.get("confidence"), (int, float)):
            confidences.append(float(prediction["confidence"]))
    return mask, confidences


def encode_jpeg(rgb: np.ndarray) -> bytes:
    buffer = io.BytesIO()
    Image.fromarray(rgb).save(buffer, format="JPEG", quality=92)
    return buffer.getvalue()


def load_rgb(path: Path) -> tuple[np.ndarray, np.ndarray]:
    """RGB pixels plus a valid-pixel mask (alpha > 0 for RGBA rasters)."""
    Image.MAX_IMAGE_PIXELS = None
    source = Image.open(path)
    source.load()
    if source.mode == "RGBA":
        rgba = np.asarray(source)
        rgb = np.ascontiguousarray(rgba[:, :, :3])
        valid = rgba[:, :, 3] > 0
    else:
        rgb = np.asarray(source.convert("RGB"))
        valid = np.ones(rgb.shape[:2], dtype=bool)
    source.close()
    return rgb, valid


def tile_starts(length: int, size: int, stride: int) -> list[int]:
    if length <= size:
        return [0]
    starts = list(range(0, length - size, stride))
    starts.append(length - size)
    return starts


def shift_any(mask: np.ndarray, grow: bool) -> np.ndarray:
    padded = np.pad(mask, 1, mode="constant", constant_values=not grow)
    out = mask.copy()
    for dy in (0, 1, 2):
        for dx in (0, 1, 2):
            if dy == 1 and dx == 1:
                continue
            window = padded[dy:dy + mask.shape[0], dx:dx + mask.shape[1]]
            if grow:
                out |= window
            else:
                out &= window
    return out


def close_mask(mask: np.ndarray, iterations: int) -> np.ndarray:
    for _ in range(iterations):
        mask = shift_any(mask, grow=True)
    for _ in range(iterations):
        mask = shift_any(mask, grow=False)
    return mask


def save_mask(mask: np.ndarray, path: Path) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    Image.fromarray(mask.astype(np.uint8), mode="L").save(path, format="PNG", optimize=True)


def predict_raster(raster_path: Path, mask_path: Path, args: argparse.Namespace, api: dict) -> dict:
    """Tile the raster, run every tile with imagery through the workflow, stitch a 0/1 mask PNG.

    Where tiles overlap, a pixel is mangrove when at least half of the tiles covering it say so.
    """
    rgb, valid = load_rgb(raster_path)
    height, width = rgb.shape[:2]
    size = max(64, args.tile_size)
    stride = max(1, size - max(0, min(args.overlap, size // 2)))
    windows = [
        (x, y)
        for y in tile_starts(height, size, stride)
        for x in tile_starts(width, size, stride)
        if valid[y:y + size, x:x + size].any()
    ]

    votes = np.zeros((height, width), dtype=np.uint8)
    hits = np.zeros((height, width), dtype=np.uint8)
    confidences: list[float] = []
    lock = threading.Lock()

    def run_tile(window: tuple[int, int]) -> None:
        x, y = window
        patch = rgb[y:y + size, x:x + size]
        h, w = patch.shape[:2]
        output = run_workflow(encode_jpeg(patch), **api)
        segmentation = find_segmentation(output, args.output or None)
        tile_mask, tile_conf = mangrove_mask(segmentation, h, w, args.class_set)
        with lock:
            votes[y:y + h, x:x + w] += tile_mask
            hits[y:y + h, x:x + w] += 1
            confidences.extend(tile_conf)

    with ThreadPoolExecutor(max_workers=max(1, args.parallel)) as pool:
        # list() re-raises the first tile failure here.
        list(pool.map(run_tile, windows))

    mangrove = (hits > 0) & (votes.astype(np.uint16) * 2 >= hits) & valid
    del votes, hits
    if args.close > 0:
        mangrove = close_mask(mangrove, args.close) & valid
    save_mask(mangrove, mask_path)

    valid_pixels = int(valid.sum())
    mangrove_pixels = int(mangrove.sum())
    return {
        "ok": True,
        "width": width,
        "height": height,
        "tiles": len(windows),
        "pixels_mangrove": mangrove_pixels,
        "pixels_valid": valid_pixels,
        "mangrove_coverage_percent": round(mangrove_pixels / valid_pixels * 100.0, 2) if valid_pixels else 0.0,
        "mean_confidence": round(float(np.mean(confidences)), 4) if confidences else 0.0,
        "input_size": size,
        "device": "roboflow",
        "workflow": api["workflow_id"],
    }


def predict_image(image_path: Path, mask_path: Path | None, args: argparse.Namespace, api: dict) -> dict:
    """One image, one request: the smoke-test path."""
    rgb, valid = load_rgb(image_path)
    height, width = rgb.shape[:2]
    output = run_workflow(encode_jpeg(rgb), **api)
    segmentation = find_segmentation(output, args.output or None)
    mask, confidences = mangrove_mask(segmentation, height, width, args.class_set)
    mask &= valid
    if mask_path is not None:
        save_mask(mask, mask_path)
    valid_pixels = int(valid.sum())
    return {
        "ok": True,
        "output_keys": sorted(output),
        "classes": sorted({str(p.get("class")) for p in segmentation.get("predictions", []) if isinstance(p, dict)}),
        "width": width,
        "height": height,
        "pixels_mangrove": int(mask.sum()),
        "pixels_valid": valid_pixels,
        "mangrove_coverage_percent": round(int(mask.sum()) / valid_pixels * 100.0, 2) if valid_pixels else 0.0,
        "mean_confidence": round(float(np.mean(confidences)), 4) if confidences else 0.0,
        "device": "roboflow",
        "workflow": api["workflow_id"],
    }


def main() -> int:
    parser = argparse.ArgumentParser(description="Mangrove extent from the Roboflow Workflow")
    mode = parser.add_mutually_exclusive_group(required=True)
    mode.add_argument("--raster", help="Large RGB/RGBA raster to segment tile by tile")
    mode.add_argument("--image", help="One image, one request (smoke test)")
    parser.add_argument("--mask", default=None, help="Output 0/1 mask PNG")
    parser.add_argument("--api-url", default=os.environ.get("ROBOFLOW_API_URL") or DEFAULT_API_URL)
    parser.add_argument("--workspace", default=os.environ.get("ROBOFLOW_WORKSPACE") or DEFAULT_WORKSPACE)
    parser.add_argument("--workflow", default=os.environ.get("ROBOFLOW_EXTENT_WORKFLOW") or DEFAULT_WORKFLOW)
    parser.add_argument("--output", default="", help="Workflow output name (default: auto-detect)")
    parser.add_argument("--classes", default="mangrove-area", help="Comma-separated mangrove class names")
    parser.add_argument("--tile-size", type=int, default=1024)
    parser.add_argument("--overlap", type=int, default=64)
    parser.add_argument("--parallel", type=int, default=4)
    parser.add_argument("--request-timeout", type=float, default=60.0)
    parser.add_argument("--retries", type=int, default=3)
    parser.add_argument("--close", type=int, default=0, help="Mask closing iterations for --raster")
    args = parser.parse_args()
    args.class_set = {c.strip().lower() for c in args.classes.split(",") if c.strip()}

    api = {
        "api_key": os.environ.get("ROBOFLOW_API_KEY", "").strip(),
        "workspace": args.workspace,
        "workflow_id": args.workflow,
        "api_url": args.api_url,
        "timeout": args.request_timeout,
        "retries": args.retries,
    }

    try:
        if args.raster:
            if not args.mask:
                raise RoboflowConfigError("--mask is required with --raster")
            result = predict_raster(Path(args.raster), Path(args.mask), args, api)
        else:
            result = predict_image(Path(args.image), Path(args.mask) if args.mask else None, args, api)
    except Exception as exc:  # noqa: BLE001 - reported as JSON to the PHP side
        print(json.dumps({"ok": False, "error": str(exc), "error_type": type(exc).__name__}), flush=True)
        return 1

    print(json.dumps(result), flush=True)
    return 0


if __name__ == "__main__":
    sys.exit(main())
