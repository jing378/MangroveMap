<?php

return [
    'qgis_env' => env('GDAL_QGIS_ENV', 'C:\\Program Files\\QGIS 3.44.12\\bin\\o4w_env.bat'),
    'timeout' => (int) env('GDAL_PROCESS_TIMEOUT', 7000),
    // "min-max" or "min-auto"; auto picks the zoom that matches the raster's native resolution
    // (e.g. ~22 for 5 cm drone mosaics), capped at max_tile_zoom (the map's MAP_MAX_ZOOM).
    'tile_zoom' => env('GDAL_TILE_ZOOM', '12-auto'),
    'max_tile_zoom' => (int) env('GDAL_MAX_TILE_ZOOM', 22),
    'tile_processes' => (int) env('GDAL_TILE_PROCESSES', 2),
    // Files per drone imagery batch. PHP's max_file_uploads (php.ini) must be at least this high.
    'max_upload_files' => (int) env('DRONE_MAX_UPLOAD_FILES', 100),
];
