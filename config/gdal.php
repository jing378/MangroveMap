<?php

return [
    'qgis_env' => env('GDAL_QGIS_ENV', 'C:\\Program Files\\QGIS 3.44.12\\bin\\o4w_env.bat'),
    'timeout' => (int) env('GDAL_PROCESS_TIMEOUT', 7000),
    'tile_zoom' => env('GDAL_TILE_ZOOM', '12-20'),
    'tile_processes' => (int) env('GDAL_TILE_PROCESSES', 2),
];
