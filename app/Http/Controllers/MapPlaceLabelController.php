<?php

namespace App\Http\Controllers;

use App\Services\OsmPlaceLabelService;

/** OpenStreetMap place names for the satellite map labels (public data, also used on the landing page). */
class MapPlaceLabelController extends Controller
{
    public function __invoke(OsmPlaceLabelService $labels)
    {
        return response()
            ->json(['places' => $labels->labels()])
            ->header('Cache-Control', 'public, max-age=86400');
    }
}
