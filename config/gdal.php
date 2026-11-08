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
];
