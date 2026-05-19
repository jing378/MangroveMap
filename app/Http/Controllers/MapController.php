<?php

namespace App\Http\Controllers;

use App\Models\MangroveData;
use Illuminate\Http\Request;

class MapController extends Controller
{
    public function show()
    {
        return view('map.index', [
            'mapboxToken' => config('services.mapbox.public_key')
        ]);
    }

    public function getData(Request $request)
    {
        $year = $request->query('year', date('Y'));

        $data = MangroveData::whereYear('observation_date', $year)
            ->get()
            ->map(function ($item) {
                return [
                    'type' => 'Feature',
                    'geometry' => [
                        'type' => 'Point',
                        'coordinates' => [$item->longitude, $item->latitude]
                    ],
                    'properties' => [
                        'id' => $item->id,
                        'region' => $item->region,
                        'coverage' => $item->coverage_area_km2,
                        'health' => $item->health_status,
                        'date' => $item->observation_date->format('Y-m-d')
                    ]
                ];
            });

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => $data
        ]);
    }
}
