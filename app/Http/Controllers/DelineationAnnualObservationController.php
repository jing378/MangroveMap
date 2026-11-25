<?php

namespace App\Http\Controllers;

use App\Models\Delineation;
use App\Models\DelineationAnnualObservation;
use App\Models\Genus;
use App\Services\ApprovedCoverageStats;
use App\Services\SatelliteMangroveGrid;
use App\Support\ExtentOverlap;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Read-only yearly history for the map's "Changes over time" timeline and charts.
 * Observations are written when an expert saves a boundary edit as an observed change
 * (see EndUserController::updateDelineationBoundary and DelineationObservationRecorder).
 */
class DelineationAnnualObservationController extends Controller
{
    public function index(Request $request, Delineation $delineation, SatelliteMangroveGrid $grid): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && (
            $user->isExpert()
            || $delineation->user_id === $user->id
            || ($delineation->is_approved && ! $delineation->is_rejected)
        ), 404);

        $observations = $delineation->annualObservations()
            ->with('boundaryRevisions')
            ->orderBy('year')
            ->get();
        $features = $delineation->features ?? [];
        $coverage = $observations->map(function (DelineationAnnualObservation $observation) use (&$features) {
            $features = $observation->boundaryRevisions->last()?->features ?? $features;

            return [
                'year' => $observation->year,
                'coverageAreaHa' => $observation->coverage_area_ha,
                'source' => $observation->source,
                'features' => $features,
            ];
        })->values();
        $latest = $observations->last();
        $genusIds = collect($latest?->genus_distribution ?? [])->keys();
        $genera = Genus::whereIn('id', $genusIds)->pluck('common_name', 'id');

        // Older records carry a percentage breakdown; newer ones only list the genera present.
        $genusDistribution = collect($latest?->genus_distribution ?? [])
            ->map(function ($share, $genusId) use ($genera) {
                return [
                    'label' => $genera[$genusId] ?? null,
                    'share' => (float) $share,
                ];
            })
            ->filter(fn ($item) => $item['label'] !== null && $item['share'] > 0)
            ->values();

        return response()->json([
            'coverage' => $coverage,
            'genusYear' => $latest?->year,
            'genusDistribution' => $genusDistribution,
            'generaPresent' => $latest?->genera_present ?? [],
            'previousData' => $this->previousData($delineation, $grid),
            // MangroveMap's own figure for the area, continuing the previous data on the same timeline: from
            // the year it was added (as the map's year slider counts it), its current boundary's hectares.
            'mapped' => [
                'since' => ($delineation->created_at ?? now())->year,
                'areaHa' => round(ApprovedCoverageStats::areaHectares(is_array($delineation->features) ? $delineation->features : []), 4),
                'approved' => (bool) $delineation->is_approved && ! $delineation->is_rejected,
            ],
        ]);
    }

    /**
     * "Previous data": the satellite's mangrove hectares inside this area's current boundary in every year it
     * has (Global Mangrove Watch), so the timeline can show the area before it was mapped here. Null without
     * the satellite data. Cached per boundary version.
     */
    private function previousData(Delineation $delineation, SatelliteMangroveGrid $grid): ?array
    {
        $meta = $grid->meta();
        if ($meta === null) {
            return null;
        }
        $key = 'delineation-previous-data:'.$delineation->id.':'.md5(json_encode($delineation->features).json_encode($meta['years']));
        $history = Cache::remember($key, now()->addDay(), fn () => $grid->history(
            ExtentOverlap::shapesFromFeatures(is_array($delineation->features) ? $delineation->features : [])
        ));
        if ($history === null) {
            return null;
        }

        return [
            'source' => $meta['source'],
            'sourceUrl' => $meta['source_url'],
            'license' => $meta['license'],
            'areaHa' => $history['area_ha'],
            'years' => array_map(fn (int $year, float $ha) => ['year' => $year, 'mangroveHa' => $ha], $history['years'], $history['hectares']),
        ];
    }
}
