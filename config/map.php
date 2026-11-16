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

    /*
    | "Mangrove extent by year" on the map: where mangroves stood in each year since 1985, from Global
    | Mangrove Watch satellite maps (about 30 m, CC BY 4.0). Built once by
    | scripts/gmw/build_mangrove_history.py and kept in the repository; the map draws it in the browser.
    |
    | Hidden for now ('enabled' off): the map's year slider then shows this system's own records instead
    | (approved hectares from the coverage trend, delineations and drone uploads by the year they were added),
    | and the dashboard leaves out its satellite chart. Set MAP_MANGROVE_HISTORY_ENABLED=true to bring it back.
    */
    'mangrove_history' => [
        'enabled' => (bool) env('MAP_MANGROVE_HISTORY_ENABLED', false),
        'path' => resource_path('data/mangrove-history.json'),
    ],

    /*
    | "Field conditions" card on the resident and expert dashboards: a short forecast from Open-Meteo
    | (free, no API key; non-commercial use, attribution required). Given for the user's location when the
    | browser shares it (rounded to ~1 km, cached per spot), else for the place below; the dashboard loads
    | it after the page so a slow or offline API never delays the page.
    */
    'weather' => [
        'enabled' => (bool) env('MAP_WEATHER_ENABLED', true),
        // Default place, used when the user does not share their location (Maasin City, Southern Leyte).
        'latitude' => (float) env('MAP_WEATHER_LATITUDE', 10.358),
        'longitude' => (float) env('MAP_WEATHER_LONGITUDE', 124.973),
        'place' => env('MAP_WEATHER_PLACE', 'Maasin City, Southern Leyte'),
        'url' => env('MAP_WEATHER_URL', 'https://api.open-meteo.com/v1/forecast'),
        'timezone' => 'Asia/Manila',
        'days' => 4,
        'cache_minutes' => (int) env('MAP_WEATHER_CACHE_MINUTES', 60),
        // After a failed request, wait this long before asking again (the card says it is unavailable).
        'retry_minutes' => 10,
        'timeout' => (int) env('MAP_WEATHER_TIMEOUT', 6),
    ],

];
