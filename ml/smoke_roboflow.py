#!/usr/bin/env python3
"""Live smoke test for the Roboflow extent workflow (ml/predict_roboflow.py). Calls the real API.

    ml/.venv/Scripts/python.exe ml/smoke_roboflow.py <image.jpg|png|tif>

Needs ROBOFLOW_API_KEY in the environment (read from the project .env when it is not set).
Exits 0 when the workflow answers with a decodable mangrove segmentation, 1 otherwise.
"""

from __future__ import annotations

import os
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))

import numpy as np  # noqa: E402

import predict_roboflow as rf  # noqa: E402


def api_key_from_dotenv() -> str:
    env_file = Path(__file__).resolve().parent.parent / ".env"
    if not env_file.is_file():
        return ""
    for line in env_file.read_text(encoding="utf-8").splitlines():
        if line.startswith("ROBOFLOW_API_KEY="):
            return line.split("=", 1)[1].strip().strip('"').strip("'")
    return ""


def main() -> int:
    if len(sys.argv) != 2 or not Path(sys.argv[1]).is_file():
        print("usage: smoke_roboflow.py <image>", file=sys.stderr)
        return 2

    api_key = os.environ.get("ROBOFLOW_API_KEY", "").strip() or api_key_from_dotenv()
    assert api_key, "ROBOFLOW_API_KEY is not set (environment or .env)"

    rgb, _valid = rf.load_rgb(Path(sys.argv[1]))
    height, width = rgb.shape[:2]

    output = rf.run_workflow(
        rf.encode_jpeg(rgb),
        api_key=api_key,
        workspace=os.environ.get("ROBOFLOW_WORKSPACE") or rf.DEFAULT_WORKSPACE,
        workflow_id=os.environ.get("ROBOFLOW_EXTENT_WORKFLOW") or rf.DEFAULT_WORKFLOW,
    )
    assert isinstance(output, dict) and output, "workflow returned no output"
    print("output keys:", sorted(output))

    segmentation = rf.find_segmentation(output)
    assert isinstance(segmentation.get("predictions"), list), "segmentation has no predictions list"
    image = segmentation.get("image") or {}
    assert (image.get("width"), image.get("height")) == (width, height), f"unexpected image size {image}"

    for prediction in segmentation["predictions"]:
        for key in ("class", "confidence"):
            assert key in prediction, f"prediction without '{key}'"
        assert "rle_mask" in prediction or "points" in prediction, "prediction without a mask"

    mask, confidences = rf.mangrove_mask(segmentation, height, width, {"mangrove-area"})
    assert mask.shape == (height, width) and mask.dtype == np.bool_

    print(
        f"ok: {len(segmentation['predictions'])} prediction(s), classes "
        f"{sorted({p.get('class') for p in segmentation['predictions']})}, "
        f"mangrove {mask.mean() * 100:.1f}% of {width}x{height}, confidence {confidences}"
    )
    return 0


if __name__ == "__main__":
    sys.exit(main())
