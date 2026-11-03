<?php

$venvPython = PHP_OS_FAMILY === 'Windows'
    ? base_path('ml/.venv/Scripts/python.exe')
    : base_path('ml/.venv/bin/python');

return [
    'python' => env('ML_PYTHON', file_exists($venvPython) ? $venvPython : 'python'),
    'predict_script' => env('ML_PREDICT_SCRIPT', base_path('ml/predict.py')),
    'model_path' => env('ML_MODEL_PATH', base_path('ml/models/unet_seg_model.pth')),
    'input_size' => (int) env('ML_INPUT_SIZE', 512),
    'timeout' => (int) env('ML_TIMEOUT', 180),

    // Drone orthomosaic segmentation (SegmentDroneImagery job).
    'drone' => [
        'auto_segment' => (bool) env('ML_DRONE_AUTO_SEGMENT', true),
        // Ground resolution (metres/pixel) the mosaic is resampled to before inference.
        'resolution' => (float) env('ML_DRONE_RESOLUTION', 0.15),
        // Longest side of the resampled raster; coarser resolution is used past this.
        'max_dimension' => (int) env('ML_DRONE_MAX_DIMENSION', 12000),
        'tile_overlap' => (int) env('ML_DRONE_TILE_OVERLAP', 64),
        'close_iterations' => (int) env('ML_DRONE_CLOSE_ITERATIONS', 2),
        // Polygon clean-up before features are offered as a delineation draft.
        // ~1 m keeps the boundary faithful while leaving few enough vertices to edit smoothly.
        'simplify_metres' => (float) env('ML_DRONE_SIMPLIFY_METRES', 1.0),
        'min_area_m2' => (float) env('ML_DRONE_MIN_AREA_M2', 25),
        'max_features' => (int) env('ML_DRONE_MAX_FEATURES', 20),
        'max_vertices' => (int) env('ML_DRONE_MAX_VERTICES', 300),
        'timeout' => (int) env('ML_DRONE_TIMEOUT', 3600),
    ],
];
