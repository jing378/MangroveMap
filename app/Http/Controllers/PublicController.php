<?php

namespace App\Http\Controllers;

use App\Models\Delineation;
use App\Models\PlantingRecommendation;
use App\Services\DelineationObservationRecorder;

class PublicController extends Controller
{
    /** Same cap as the logged-in Map page. */
    private const RECOMMENDATIONS_LIMIT = 300;

    /**
     * Public landing page. Every figure comes from expert-approved delineations, in hectares.
     */
    public function index()
    {
        $delineations = Delineation::with([
            'user:id,name',
            'annualObservations:id,delineation_id,year,coverage_area_ha',
        ])
            ->approved()
            ->latest('approved_at')
            ->get();

        $records = $delineations->map(fn(Delineation $delineation) => $this->publicPayload($delineation));
        $features = $records->flatMap(fn(array $record) => $record['features']);

        // Same visibility as for residents: freely drawn areas, or ones on a still-approved delineation.
        $recommendations = PlantingRecommendation::query()
            ->where(fn($query) => $query
                ->whereNull('delineation_id')
                ->orWhereHas('delineation', fn($delineation) => $delineation->approved()))
            ->with(['user:id,name', 'delineation:id,name'])
            ->latest()
            ->limit(self::RECOMMENDATIONS_LIMIT)
            ->get()
            ->map(fn(PlantingRecommendation $recommendation) => $this->publicRecommendationPayload($recommendation))
            ->values();

        return view('index', [
            'delineations' => $records->values(),
            'plantingRecommendations' => $recommendations,
            'suitabilityLevels' => PlantingRecommendation::SUITABILITY_LEVELS,
            'mappedAreaHa' => round($records->sum('area_ha'), 2),
            'barangayCount' => $features
                ->map(fn(array $feature) => $feature['barangay'] ? $feature['barangay'] . '|' . $feature['municipality'] : null)
                ->filter()
                ->unique()
                ->count(),
            'generaCount' => $features->flatMap(fn(array $feature) => $feature['genera'])->unique()->count(),
            'contributorCount' => $delineations->pluck('user_id')->unique()->count(),
        ]);
    }

    /**
     * Site notes are the expert's working notes and stay private, as on the logged-in map.
     */
    private function publicRecommendationPayload(PlantingRecommendation $recommendation): array
    {
        $features = collect($recommendation->features ?? [])
            ->filter(fn($feature) => is_array($feature) && is_array($feature['coords'] ?? null) && count($feature['coords']) >= 3)
            ->map(fn(array $feature) => [
                'coords' => $feature['coords'],
                'area_ha' => DelineationObservationRecorder::areaHectares([['type' => 'area', 'coords' => $feature['coords']]]),
                'location' => $feature['location'] ?? null,
            ])
            ->values();

        return [
            'id' => $recommendation->id,
            'delineation_id' => $recommendation->delineation_id,
            'delineation_name' => $recommendation->delineation?->name,
            'name' => $recommendation->name,
            'suitability' => $recommendation->suitability,
            'suitability_label' => PlantingRecommendation::SUITABILITY_LEVELS[$recommendation->suitability] ?? $recommendation->suitability,
            'recommended_genera' => $recommendation->recommended_genera ?? [],
            'recommendation' => $recommendation->recommendation,
            'expert_name' => $recommendation->user?->name,
            'updated_at' => $recommendation->updated_at?->toIso8601String(),
            'area_ha' => round($features->sum('area_ha'), 4),
            'features' => $features,
        ];
    }

    /**
     * Only what the public map needs: no emails, reviewer IDs, or revision data.
     */
    private function publicPayload(Delineation $delineation): array
    {
        $features = collect($delineation->features ?? [])
            ->filter(fn($feature) => is_array($feature) && isset($feature['coords']))
            ->map(function (array $feature) {
                $details = $feature['location_details'] ?? [];

                return [
                    'type' => $feature['type'] ?? 'area',
                    'coords' => $feature['coords'],
                    'area_ha' => DelineationObservationRecorder::areaHectares([$feature + ['type' => 'area']]),
                    'genera' => DelineationObservationRecorder::generaPresent([$feature]),
                    'location' => $feature['location'] ?? null,
                    'barangay' => $details['barangay']['name'] ?? null,
                    'municipality' => $details['municipality']['name'] ?? null,
                ];
            })
            ->values();

        return [
            'id' => $delineation->id,
            'name' => $delineation->name,
            'notes' => $delineation->notes,
            'approved_at' => $delineation->approved_at?->toIso8601String(),
            'created_at' => $delineation->created_at?->toIso8601String(),
            'user' => ['name' => $delineation->user?->name],
            'area_ha' => round($features->sum('area_ha'), 4),
            'genera' => $features->flatMap(fn(array $feature) => $feature['genera'])->unique()->values(),
            'features' => $features,
            'coverage_trend' => $delineation->annualObservations
                ->sortBy('year')
                ->map(fn($observation) => ['year' => $observation->year, 'area_ha' => $observation->coverage_area_ha])
                ->values(),
        ];
    }
}
