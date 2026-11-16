<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * Where mangroves stood in each year since 1985, for the map's "Mangrove extent by year" slider.
 * Public Global Mangrove Watch data (CC BY 4.0), prepared by scripts/gmw/build_mangrove_history.py.
 */
class MapMangroveHistoryController extends Controller
{
    public function __invoke(Request $request)
    {
        $path = config('map.mangrove_history.path');

        if (! is_string($path) || ! is_file($path)) {
            return response()->json(['message' => 'The yearly mangrove extent data is not installed.'], 404);
        }

        $body = (string) file_get_contents($path);
        $headers = [
            'Content-Type' => 'application/json',
            'Cache-Control' => 'public, max-age=86400',
            'Vary' => 'Accept-Encoding',
        ];

        // The file is mostly base64 and shrinks several times over; Apache does not compress it for us.
        if (function_exists('gzencode') && in_array('gzip', $request->getEncodings(), true)) {
            $body = gzencode($body, 6);
            $headers['Content-Encoding'] = 'gzip';
        }

        return response($body, 200, $headers);
    }
}
