#!/usr/bin/env python3
"""Run mangrove U-Net segmentation on a single image and print JSON."""

from __future__ import annotations

import os
import sys

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

import argparse
import json
from pathlib import Path

import numpy as np
import torch
import torch.nn.functional as F
from PIL import Image
import segmentation_models_pytorch as smp

IMAGENET_MEAN = torch.tensor([0.485, 0.456, 0.406]).view(1, 3, 1, 1)
IMAGENET_STD = torch.tensor([0.229, 0.224, 0.225]).view(1, 3, 1, 1)
MANGROVE_CLASS = 1


def letterbox(image: Image.Image, size: int) -> tuple[Image.Image, dict]:
    src_w, src_h = image.size
    scale = min(size / src_w, size / src_h)
    new_w = max(1, int(round(src_w * scale)))
    new_h = max(1, int(round(src_h * scale)))
    resized = image.resize((new_w, new_h), Image.Resampling.BILINEAR)
    canvas = Image.new("RGB", (size, size), (0, 0, 0))
    pad_x = (size - new_w) // 2
    pad_y = (size - new_h) // 2
    canvas.paste(resized, (pad_x, pad_y))
    return canvas, {
        "pad_x": pad_x,
        "pad_y": pad_y,
        "new_w": new_w,
        "new_h": new_h,
        "src_w": src_w,
        "src_h": src_h,
    }


def load_state_dict(path: Path, device: torch.device) -> dict:
    ckpt = torch.load(path, map_location=device, weights_only=False)
    if isinstance(ckpt, dict):
        for key in ("state_dict", "model_state_dict", "model"):
            inner = ckpt.get(key)
            if isinstance(inner, dict) and any(
                isinstance(v, torch.Tensor) for v in inner.values()
            ):
                ckpt = inner
                break
    if not isinstance(ckpt, dict):
        if hasattr(ckpt, "state_dict"):
            ckpt = ckpt.state_dict()
        else:
            raise RuntimeError(f"Unsupported checkpoint type: {type(ckpt)}")

    cleaned = {}
    for key, value in ckpt.items():
        if key == "_metadata" or not isinstance(value, torch.Tensor):
            continue
        if key.startswith("module."):
            key = key[len("module.") :]
        cleaned[key] = value
    return cleaned


def load_model(weights_path: Path, device: torch.device) -> torch.nn.Module:
    model = smp.Unet(
        encoder_name="resnet34",
        encoder_weights=None,
        in_channels=3,
        classes=2,
        activation=None,
    )
    state = load_state_dict(weights_path, device)
    missing, unexpected = model.load_state_dict(state, strict=False)
    missing = [k for k in missing if "num_batches_tracked" not in k]
    if missing:
        raise RuntimeError(f"Missing weights: {missing[:8]}")
    if unexpected:
        raise RuntimeError(f"Unexpected weights: {unexpected[:8]}")
    model.to(device)
    model.eval()
    return model


def preprocess(image: Image.Image, size: int, device: torch.device):
    boxed, meta = letterbox(image.convert("RGB"), size)
    array = np.asarray(boxed, dtype=np.float32) / 255.0
    tensor = torch.from_numpy(array).permute(2, 0, 1).unsqueeze(0)
    tensor = (tensor - IMAGENET_MEAN) / IMAGENET_STD
    return tensor.to(device), meta


def crop_valid(mask: np.ndarray, meta: dict) -> np.ndarray:
    y0, y1 = meta["pad_y"], meta["pad_y"] + meta["new_h"]
    x0, x1 = meta["pad_x"], meta["pad_x"] + meta["new_w"]
    cropped = mask[y0:y1, x0:x1]
    return np.array(
        Image.fromarray(cropped).resize((meta["src_w"], meta["src_h"]), Image.Resampling.NEAREST)
    )


def extract_boundary(mask: np.ndarray, thickness: int = 2) -> np.ndarray:
    """Extract boundary contour of the binary mask using pure NumPy."""
    is_mangrove = (mask == MANGROVE_CLASS)
    if not np.any(is_mangrove):
        return np.zeros_like(is_mangrove, dtype=bool)

    # 4-connectivity interior check: a pixel is interior if all 4 orthogonal neighbors are mangrove
    padded = np.pad(is_mangrove, 1, mode="constant", constant_values=False)
    interior = (
        padded[1:-1, 1:-1]
        & padded[:-2, 1:-1]
        & padded[2:, 1:-1]
        & padded[1:-1, :-2]
        & padded[1:-1, 2:]
    )
    edge = is_mangrove & (~interior)

    if thickness > 1:
        dilated = edge.copy()
        for _ in range(thickness - 1):
            pad_edge = np.pad(dilated, 1, mode="constant", constant_values=False)
            dilated = (
                pad_edge[1:-1, 1:-1]
                | pad_edge[:-2, 1:-1]
                | pad_edge[2:, 1:-1]
                | pad_edge[1:-1, :-2]
                | pad_edge[1:-1, 2:]
                | pad_edge[:-2, :-2]
                | pad_edge[:-2, 2:]
                | pad_edge[2:, :-2]
                | pad_edge[2:, 2:]
            )
        return dilated
    return edge


def make_overlay(original: Image.Image, mangrove_mask: np.ndarray) -> Image.Image:
    base = original.convert("RGBA")
    h, w = mangrove_mask.shape[:2]

    # Adaptive boundary stroke thickness based on image resolution (min 2px, up to 5px)
    thickness = max(2, min(5, int(round(min(w, h) / 250))))
    boundary = extract_boundary(mangrove_mask, thickness=thickness)

    color = np.zeros((h, w, 4), dtype=np.uint8)
    is_mangrove = (mangrove_mask == MANGROVE_CLASS)

    # Canopy fill: semi-transparent emerald tint (so aerial foliage details remain visible)
    color[is_mangrove] = (30, 158, 98, 90)

    # Delineation perimeter stroke: vibrant fluorescent lime-green (RGB 198, 255, 0)
    color[boundary] = (198, 255, 0, 255)

    overlay = Image.fromarray(color, mode="RGBA")
    return Image.alpha_composite(base, overlay).convert("RGB")



def predict(image_path: Path, weights_path: Path, overlay_path: Path | None, size: int) -> dict:
    device = torch.device("cuda" if torch.cuda.is_available() else "cpu")
    model = load_model(weights_path, device)
    original = Image.open(image_path).convert("RGB")
    tensor, meta = preprocess(original, size, device)

    with torch.no_grad():
        logits = model(tensor)
        if logits.shape[-2:] != (size, size):
            logits = F.interpolate(logits, size=(size, size), mode="bilinear", align_corners=False)
        probs = torch.softmax(logits, dim=1)[0]
        labels = torch.argmax(probs, dim=0).cpu().numpy().astype(np.uint8)
        mangrove_prob = probs[MANGROVE_CLASS].cpu().numpy()

    labels = crop_valid(labels, meta)
    mangrove_prob = crop_valid((mangrove_prob * 255).astype(np.uint8), meta).astype(np.float32) / 255.0

    mangrove_pixels = int((labels == MANGROVE_CLASS).sum())
    total_pixels = int(labels.size)
    coverage = (mangrove_pixels / total_pixels * 100.0) if total_pixels else 0.0
    if mangrove_pixels:
        mean_conf = float(mangrove_prob[labels == MANGROVE_CLASS].mean())
    else:
        mean_conf = float(mangrove_prob.mean())

    if overlay_path:
        overlay_path.parent.mkdir(parents=True, exist_ok=True)
        overlay_img = make_overlay(original, labels)
        # For ultra-high-res drone photos, constrain preview overlay to 2048px for instant browser display
        max_dim = 2048
        if max(overlay_img.width, overlay_img.height) > max_dim:
            overlay_img.thumbnail((max_dim, max_dim), Image.Resampling.LANCZOS)
        overlay_img.save(overlay_path, format="PNG", optimize=True)

    return {
        "ok": True,
        "label": "Mangrove" if coverage >= 1.0 else "No mangrove detected",
        "mangrove_coverage_percent": round(coverage, 2),
        "mean_confidence": round(mean_conf, 4),
        "pixels_mangrove": mangrove_pixels,
        "pixels_total": total_pixels,
        "input_size": size,
        "device": str(device),
        "classes": ["background", "mangrove"],
    }


def main() -> int:
    parser = argparse.ArgumentParser(description="Mangrove U-Net segmentation")
    parser.add_argument("--image", required=True)
    parser.add_argument("--weights", required=True)
    parser.add_argument("--overlay", default=None)
    parser.add_argument("--size", type=int, default=512)
    args = parser.parse_args()

    try:
        result = predict(
            Path(args.image),
            Path(args.weights),
            Path(args.overlay) if args.overlay else None,
            args.size,
        )
    except Exception as exc:
        print(json.dumps({"ok": False, "error": str(exc)}), flush=True)
        return 1

    print(json.dumps(result), flush=True)
    return 0


if __name__ == "__main__":
    sys.exit(main())
