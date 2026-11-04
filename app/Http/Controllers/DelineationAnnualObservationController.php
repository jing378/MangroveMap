<?php

namespace App\Http\Controllers;

use App\Models\Delineation;
use App\Models\DelineationAnnualObservation;
use App\Models\Genus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only yearly history for the map's "Changes over time" timeline and charts.
 * Observations are written when an expert saves a boundary edit as an observed change
 * (see EndUserController::updateDelineationBoundary and DelineationObservationRecorder).
 */
class DelineationAnnualObservationController extends Controller
{
    public function index(Request $request, Delineation $delineation): JsonResponse
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
            ->filter(fn($item) => $item['label'] !== null && $item['share'] > 0)
            ->values();

        return response()->json([
            'coverage' => $coverage,
            'genusYear' => $latest?->year,
            'genusDistribution' => $genusDistribution,
            'generaPresent' => $latest?->genera_present ?? [],
        ]);
    }
}
