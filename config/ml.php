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
        // Which model delineates the mangrove extent: "unet" = the U-Net (ml.model_path); the canopy model
        // then classifies the genus and pinpoints the canopies inside that extent. "yolo" = the canopy model
        // (ml.genus weights, best.pt) delineates too: the extent is the union of every canopy it finds at
        // >= ml.genus.extent_confidence, falling back to the U-Net when no weights exist or the run fails.
        // "roboflow" = the hosted Roboflow Workflow (ml.roboflow, RF-DETR semantic segmentation), falling
        // back to the U-Net when ROBOFLOW_API_KEY is empty or the run fails.
        'extent_model' => env('ML_DRONE_EXTENT_MODEL', 'unet'),
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

    // Canopy model (Ultralytics YOLO segmentation whose classes are genera, ml/predict_genus.py), run on
    // drone imagery after the U-Net. It gives the genus suggestion and pinpoints every canopy it finds
    // inside the U-Net extent (canopies.json, shown on the map); with drone.extent_model = yolo it draws
    // the mangrove extent itself. Weights: the newest
    // active "classification" AI model (.pt), else this path. Skipped when neither exists. The genus is
    // only a suggestion: the owner still picks it before submitting.
    'genus' => [
        'enabled' => (bool) env('ML_GENUS_SUGGEST', true),
        // Which canopy model the genus job (ClassifyDroneImagery) runs: "yolo" = the weights below, or
        // "roboflow" = the hosted canopy Workflow (ml.roboflow.genus_workflow, RF-DETR instance segmentation),
        // falling back to the YOLO weights when ROBOFLOW_API_KEY is empty or the run fails. Everything after
        // the detections (canopies, genus map, recount, suggestion) is the same for both.
        'model' => env('ML_GENUS_MODEL', 'yolo'),
        'script' => env('ML_GENUS_SCRIPT', base_path('ml/predict_genus.py')),
        'model_path' => env('ML_GENUS_MODEL_PATH', base_path('ml/models/mangrove_classifier.pt')),
        'input_size' => (int) env('ML_GENUS_INPUT_SIZE', 640),
        // Ground resolution (metres/pixel) the mosaic is resampled to for the genus job (ClassifyDroneImagery);
        // never finer than the mosaic, capped by drone.max_dimension. At the 0.15 m of the mangrove-area step
        // crowns merge into stands (a Bontoc survey: 3 "canopies" of up to 1,159 m²; at 0.05 m: 28, median 15 m²).
        'resolution' => (float) env('ML_GENUS_RESOLUTION', 0.05),
        // Pixels neighbouring tiles overlap; crowns on a tile border are joined instead of cut straight.
        'tile_overlap' => (int) env('ML_GENUS_TILE_OVERLAP', 160),
        'confidence' => (float) env('ML_GENUS_CONFIDENCE', 0.1),
        // Canopies found below this confidence are left out of the mangrove extent (drone.extent_model = yolo).
        'extent_confidence' => (float) env('ML_YOLO_EXTENT_CONFIDENCE', 0.25),
        // Pinpointed canopies: the smallest crown that counts (ground m²) and how many are kept (largest first).
        'canopy_min_area_m2' => (float) env('ML_CANOPY_MIN_AREA_M2', 1.0),
        'canopy_max' => (int) env('ML_CANOPY_MAX', 3000),
        // Only canopies the model is at least this sure of get a pin (weak guesses are often the wrong genus).
        'canopy_confidence' => (float) env('ML_CANOPY_CONFIDENCE', 0.4),
        // A canopy covered by more confident canopies for at least this share of its area is a duplicate
        // (the same trees detected again, often as another genus) and is dropped.
        'canopy_overlap' => (float) env('ML_CANOPY_OVERLAP', 0.5),
        // Lower threshold for the per-genus probabilities the review editor always shows (experimental).
        'probability_confidence' => (float) env('ML_GENUS_PROBABILITY_CONFIDENCE', 0.01),
        'timeout' => (int) env('ML_GENUS_TIMEOUT', 1800),
        // Patches of one genus smaller than this (ground m²) are ignored: the model labels thin slivers
        // along canopy/mud edges with random genera (e.g. "Nypa" fringes); real stands are larger.
        'min_patch_m2' => (float) env('ML_GENUS_MIN_PATCH_M2', 2.0),
        // Recounting the genus inside the owner's edited areas from the saved genus map (no model run).
        'recount_timeout' => (int) env('ML_GENUS_RECOUNT_TIMEOUT', 60),
    ],

    // Mangrove extent from a hosted Roboflow Workflow (drone.extent_model = roboflow), ml/predict_roboflow.py.
    // The raster is cut into tiles, each sent to the workflow (input "image"); its output is a semantic
    // segmentation whose masks come back as COCO RLE. The key is passed to Python in the environment only.
    'roboflow' => [
        'script' => env('ML_ROBOFLOW_SCRIPT', base_path('ml/predict_roboflow.py')),
        'api_key' => env('ROBOFLOW_API_KEY'),
        'api_url' => env('ROBOFLOW_API_URL', 'https://serverless.roboflow.com'),
        'workspace' => env('ROBOFLOW_WORKSPACE', 'denje-s-works'),
        'workflow' => env('ROBOFLOW_EXTENT_WORKFLOW', 'mangrove-area-delineation-3-rfdetr-sem-small-t1-vmangrove-area-delineation-3-rfdetr-sem-small-t1-logic'),
        // Workflow output holding the segmentation; empty = the first output that looks like one.
        'output' => env('ROBOFLOW_EXTENT_OUTPUT', ''),
        // Classes that count as mangrove (comma-separated).
        'classes' => env('ROBOFLOW_EXTENT_CLASSES', 'mangrove-area'),
        'tile_size' => (int) env('ROBOFLOW_TILE_SIZE', 1024),
        'tile_overlap' => (int) env('ROBOFLOW_TILE_OVERLAP', 64),
        // Tiles sent at once.
        'parallel' => (int) env('ROBOFLOW_PARALLEL', 4),
        'request_timeout' => (int) env('ROBOFLOW_REQUEST_TIMEOUT', 60),
        'retries' => (int) env('ROBOFLOW_RETRIES', 3),
        'timeout' => (int) env('ROBOFLOW_TIMEOUT', 3600),

        // Canopies and genus from a second hosted Workflow (genus.model = roboflow), run by ml/predict_genus.py
        // --roboflow on the genus tiles (genus.input_size, genus.tile_overlap). Each detection comes back as
        // {class: genus, confidence, points: polygon}; the classes are mapped by name onto genus_classes
        // (detections of any other class are ignored, so list every genus the workflow can return).
        'genus_workflow' => env('ROBOFLOW_GENUS_WORKFLOW', 'mangrove-canopy-3-rfdetr-seg-small-t1-vmangrove-canopy-3-rfdetr-seg-small-t1-logic'),
        'genus_output' => env('ROBOFLOW_GENUS_OUTPUT', ''),
        'genus_classes' => env('ROBOFLOW_GENUS_CLASSES', 'Avicennia,Nypa,Rhizophora,Sonneratia'),
    ],
];
