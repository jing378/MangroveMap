<?php

namespace App\Http\Controllers;

use App\Models\Delineation;
use App\Models\MangroveData;
use App\Models\Genus;
use Illuminate\Support\Facades\Auth;

class PublicController extends Controller
{
    public function index()
    {
        $globalCoverage = MangroveData::sum('coverage_area_km2') ?? 0;
        $protectedAreas = MangroveData::distinct('region')->count();
        $genusCount = Genus::count() ?? 0;
        $lastUpdate = MangroveData::latest('observation_date')->first()?->observation_date?->format('M d, Y') ?? 'Today';

        // Regional coverage
        $coverageByRegion = MangroveData::selectRaw('region, SUM(coverage_area_km2) as total')
            ->groupBy('region')
            ->pluck('total')
            ->toArray();

        $regions = MangroveData::selectRaw('region, SUM(coverage_area_km2) as total')
            ->groupBy('region')
            ->pluck('region')
            ->toArray();

        // Historical trends
        $coverageTrends = MangroveData::selectRaw('SUM(coverage_area_km2) as total')
            ->whereRaw('observation_date >= DATE_SUB(NOW(), INTERVAL 9 YEAR)')
            ->groupByRaw('YEAR(observation_date)')
            ->orderByRaw('YEAR(observation_date)')
            ->pluck('total')
            ->toArray();

        $years = range(date('Y') - 9, date('Y'));
        $delineations = Delineation::with('user:id,name')
            ->approved()
            ->latest('approved_at')
            ->get();

        return view('index', [
            'globalCoverage' => $globalCoverage,
            'protectedAreas' => $protectedAreas,
            'genusCount' => $genusCount,
            'lastUpdate' => $lastUpdate,
            'coverageByRegion' => $coverageByRegion,
            'regions' => $regions,
            'coverageTrends' => $coverageTrends,
            'years' => $years,
            'delineations' => $delineations,
        ]);
    }
}
