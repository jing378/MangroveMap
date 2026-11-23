#!/usr/bin/env python3
"""Live smoke test for the Roboflow canopy (genus) workflow used by ml/predict_genus.py --roboflow.
Calls the real API.

    ml/.venv/Scripts/python.exe ml/smoke_roboflow_genus.py <image.jpg|png|tif>

Needs ROBOFLOW_API_KEY in the environment (read from the project .env when it is not set).
Exits 0 when the workflow answers with genus detections that decode into masks, 1 otherwise.
"""

from __future__ import annotations

import os
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))

import numpy as np  # noqa: E402

import predict_genus as pg  # noqa: E402
import predict_roboflow as rf  # noqa: E402
from smoke_roboflow import api_key_from_dotenv  # noqa: E402


def main() -> int:
    if len(sys.argv) != 2 or not Path(sys.argv[1]).is_file():
        print("usage: smoke_roboflow_genus.py <image>", file=sys.stderr)
        return 2

    api_key = os.environ.get("ROBOFLOW_API_KEY", "").strip() or api_key_from_dotenv()
    assert api_key, "ROBOFLOW_API_KEY is not set (environment or .env)"
    api = {
        "api_key": api_key,
        "workspace": os.environ.get("ROBOFLOW_WORKSPACE") or rf.DEFAULT_WORKSPACE,
        "workflow_id": os.environ.get("ROBOFLOW_GENUS_WORKFLOW") or pg.DEFAULT_CANOPY_WORKFLOW,
    }
    names = [c.strip() for c in (os.environ.get("ROBOFLOW_GENUS_CLASSES") or pg.DEFAULT_CANOPY_CLASSES).split(",") if c.strip()]

    rgb, _valid = rf.load_rgb(Path(sys.argv[1]))
    height, width = rgb.shape[:2]

    # 1. The raw response: one output with a predictions list; each detection a genus with a mask.
    output = rf.run_workflow(rf.encode_jpeg(rgb), **api)
    assert isinstance(output, dict) and output, "workflow returned no output"
    print("output keys:", sorted(output))
    segmentation = rf.find_segmentation(output)
    predictions = segmentation["predictions"]
    image = segmentation.get("image") or {}
    assert (image.get("width"), image.get("height")) == (width, height), f"unexpected image size {image}"
    for prediction in predictions:
        for key in ("class", "confidence"):
            assert key in prediction, f"prediction without '{key}'"
        assert "points" in prediction or "rle_mask" in prediction, "prediction without a mask"
    classes = sorted({str(p["class"]) for p in predictions})
    unknown = [c for c in classes if c.lower() not in {n.lower() for n in names}]
    assert not unknown, f"workflow returned classes missing from ROBOFLOW_GENUS_CLASSES: {unknown}"

    # 2. The detector predict_genus.py uses: masks on the tile grid, genus indexes into `names`.
    size = max(height, width)
    tile = np.zeros((size, size, 3), dtype=np.uint8)
    tile[:height, :width] = rgb
    detect = pg.roboflow_detector(names, size, 0.0, api, None, 1)
    masks, labels, scores = detect([tile])[0]
    assert masks.shape[1:] == (size, size) and masks.dtype == np.bool_
    assert len(masks) == len(labels) == len(scores)
    assert all(0 <= int(cls) < len(names) for cls in labels)

    counts = {name: int((labels == i).sum()) for i, name in enumerate(names)}
    print(f"ok: {len(predictions)} detection(s) on {width}x{height}, by genus {counts}, "
          f"confidence {[round(float(s), 2) for s in sorted(scores, reverse=True)]}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
