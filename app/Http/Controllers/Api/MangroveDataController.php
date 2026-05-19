<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MangroveData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MangroveDataController extends Controller
{
    public function index(Request $request)
    {
        $query = MangroveData::query();

        if ($request->has('region')) {
            $query->where('region', $request->region);
        }

        if ($request->has('year')) {
            $query->whereYear('observation_date', $request->year);
        }

        return $query->with('genus')->paginate(20);
    }

    public function show(MangroveData $mangroveData)
    {
        return $mangroveData->load('genus', 'analyses');
    }

    public function store(Request $request)
    {
        abort_if(Auth::user()->role !== 'admin', 403);

        $validated = $request->validate([
            'region' => 'required|string',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'coverage_area_km2' => 'required|numeric|min:0',
            'genus_id' => 'nullable|exists:genera,id',
            'observation_date' => 'required|date',
            'health_status' => 'required|in:healthy,degraded,recovering'
        ]);

        return MangroveData::create($validated);
    }

    public function update(Request $request, MangroveData $mangroveData)
    {
        abort_if(Auth::user()->role !== 'admin', 403);

        $validated = $request->validate([
            'region' => 'sometimes|string',
            'latitude' => 'sometimes|numeric|between:-90,90',
            'longitude' => 'sometimes|numeric|between:-180,180',
            'coverage_area_km2' => 'sometimes|numeric|min:0',
            'genus_id' => 'nullable|exists:genera,id',
            'health_status' => 'sometimes|in:healthy,degraded,recovering'
        ]);

        $mangroveData->update($validated);
        return $mangroveData;
    }

    public function destroy(MangroveData $mangroveData)
    {
        abort_if(Auth::user()->role !== 'admin', 403);

        $mangroveData->delete();
        return response()->json(null, 204);
    }
}
