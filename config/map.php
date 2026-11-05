<?php

return [

    /*
    | Place-name labels drawn over the satellite basemap, from OpenStreetMap (towns, villages, hamlets...).
    | They are downloaded once from the Overpass API into storage_path('app/private/map/...') and served from
    | there; refresh them with `php artisan map:sync-place-labels`.
    */
    'place_labels' => [
        // [south, west, north, east]: Southern Leyte with a margin for the neighbouring towns.
        'bbox' => [9.85, 124.65, 10.65, 125.45],
        'overpass_url' => env('MAP_OVERPASS_URL', 'https://overpass-api.de/api/interpreter'),
        'timeout' => (int) env('MAP_OVERPASS_TIMEOUT', 90),
        'storage_path' => 'map/osm-place-labels.json',
    ],

];
