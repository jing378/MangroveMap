<?php

namespace App\Services;

use App\Models\Delineation;
use Illuminate\Support\Collection;

/**
 * Dashboard figures from expert-approved delineations, in hectares, measured the
 * same way as the public landing page and the map.
 */
class ApprovedCoverageStats
{
    /**
     * @return array{
     *     area_ha: float,
     *     area_count: int,
     *     genera: array<string, int>,
     *     trend: array<int, float>,
     * }
     */
    public function summary(): array
    {
        $delineations = Delineation::approved()
            ->with('annualObservations:id,delineation_id,year,coverage_area_ha')
            ->get(['id', 'features', 'created_at']);

        $areas = $delineations->mapWithKeys(fn (Delineation $d) => [$d->id => self::areaHectares($d->features ?? [])]);

        return [
            'area_ha' => round($areas->sum(), 2),
            'area_count' => $delineations->count(),
            'genera' => $this->generaCounts($delineations),
            'trend' => $this->trend($delineations, $areas),
        ];
    }

    /**
     * Features without a type are treated as areas, as on the landing page.
     */
    public static function areaHectares(array $features): float
    {
        $areas = collect($features)
            ->filter(fn ($feature) => is_array($feature) && isset($feature['coords']))
            ->map(fn (array $feature) => $feature + ['type' => 'area'])
            ->all();

        return DelineationObservationRecorder::areaHectares($areas);
    }

    /**
     * Number of approved areas each genus is recorded in, most common first.
     *
     * @return array<string, int>
     */
    private function generaCounts(Collection $delineations): array
    {
        return $delineations
            ->flatMap(fn (Delineation $d) => DelineationObservationRecorder::generaPresent(
                array_filter($d->features ?? [], 'is_array')
            ))
            ->countBy()
            ->sortDesc()
            ->all();
    }

    /**
     * Total approved area per year, from the year the first area was drawn to now.
     * Each area counts from the year it was drawn, at its latest yearly observation
     * up to that year (or its earliest one, before any was recorded). The current
     * year uses today's boundaries, so the line ends at the total area figure.
     *
     * @return array<int, float>
     */
    private function trend(Collection $delineations, Collection $areas): array
    {
        if ($delineations->isEmpty()) {
            return [];
        }

        $currentYear = now()->year;
        $firstYear = min($currentYear, (int) $delineations->min(fn (Delineation $d) => $d->created_at?->year ?? $currentYear));

        $trend = [];
        for ($year = $firstYear; $year <= $currentYear; $year++) {
            $total = 0.0;
            foreach ($delineations as $delineation) {
                if (($delineation->created_at?->year ?? $currentYear) > $year) {
                    continue;
                }

                $observations = $delineation->annualObservations->sortBy('year');
                if ($year === $currentYear || $observations->isEmpty()) {
                    $total += $areas[$delineation->id];

                    continue;
                }

                $observation = $observations->last(fn ($o) => $o->year <= $year) ?? $observations->first();
                $total += (float) $observation->coverage_area_ha;
            }
            $trend[$year] = round($total, 2);
        }

        return $trend;
    }
}
