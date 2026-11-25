<?php

return [
    'qgis_env' => env('GDAL_QGIS_ENV', 'C:\\Program Files\\QGIS 3.44.12\\bin\\o4w_env.bat'),
    // Linux only: the Python that has GDAL's osgeo_utils (apt package python3-gdal).
    // This is NOT ml/.venv, which has no GDAL. Windows uses QGIS's Python via qgis_env instead.
    'python' => env('GDAL_PYTHON', 'python3'),
    'timeout' => (int) env('GDAL_PROCESS_TIMEOUT', 7000),
    // "min-max" or "min-auto"; auto picks the zoom that matches the raster's native resolution
    // (e.g. ~22 for 5 cm drone mosaics), capped at max_tile_zoom (the map's MAP_MAX_ZOOM).
    'tile_zoom' => env('GDAL_TILE_ZOOM', '12-auto'),
    'max_tile_zoom' => (int) env('GDAL_MAX_TILE_ZOOM', 22),
    'tile_processes' => (int) env('GDAL_TILE_PROCESSES', 2),
    // Longest side (px) of the PNG preview shown in the drone imagery review editor.
    'preview_max_dimension' => (int) env('GDAL_PREVIEW_MAX_DIMENSION', 2048),
    // Files per drone imagery batch. PHP's max_file_uploads (php.ini) must be at least this high.
    'max_upload_files' => (int) env('DRONE_MAX_UPLOAD_FILES', 100),
    // Drone photos (JPG/PNG, see DronePhotoGeoreferencer) per upload: a flight has hundreds of small ones.
    // They only go through the chunked upload, so PHP's max_file_uploads does not apply.
    'max_upload_photos' => (int) env('DRONE_MAX_UPLOAD_PHOTOS', 500),
    // Photos are scaled to this ground resolution (m/pixel) before mosaicking (never finer than the photo):
    // 2 cm is about the map's deepest zoom, and full-size photos (<1 cm) would make a huge mosaic.
    'photo_resolution_m' => (float) env('DRONE_PHOTO_RESOLUTION_M', 0.02),
    // Share of each photo's width and height kept, around its centre: the edges are the most distorted
    // and carry the dark lens corners, and overlapping neighbours cover them.
    'photo_keep_fraction' => (float) env('DRONE_PHOTO_KEEP_FRACTION', 0.8),
    // Files of one batch must lie within this distance (m) of each other, directly or through other files.
    // A batch is one mosaic, so files from different places (e.g. Macrohon + Bontoc) are rejected.
    'batch_max_gap_m' => (float) env('DRONE_BATCH_MAX_GAP_M', 1000),
    // Drone Upload sends files in slices of this many MB, several at once (upload_parallel), resuming after
    // dropped connections. Each slice is one request, so the web server's body limit must allow it.
    'upload_chunk_mb' => (int) env('DRONE_UPLOAD_CHUNK_MB', 8),
    'upload_parallel' => (int) env('DRONE_UPLOAD_PARALLEL', 4),
    // Tile Explorer: tiles with at least this much mangrove (% of the tile's imagery) are labelled "Mangrove".
    'tile_explorer' => [
        'mangrove_min_percent' => (float) env('TILE_EXPLORER_MANGROVE_MIN_PERCENT', 5),
    ],
];
