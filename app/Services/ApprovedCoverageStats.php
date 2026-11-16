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
     *     trend_by_municipality: array<string, array<int, float>>,
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
            'trend_by_municipality' => $this->trendByMunicipality($delineations, $areas),
        ];
    }

    /**
     * The municipality a delineation is in: that of its first feature matched to a barangay
     * (BoundaryResolver's location_details). Null when none was matched.
     */
    public static function municipalityName(array $features): ?string
    {
        foreach ($features as $feature) {
            $name = is_array($feature) ? ($feature['location_details']['municipality']['name'] ?? null) : null;
            if (is_string($name) && trim($name) !== '') {
                return trim($name);
            }
        }

        return null;
    }

    /**
     * The same trend for each municipality with approved areas, by name, over the same years as the
     * overall trend. Areas not matched to a barangay are only in the overall trend.
     *
     * @return array<string, array<int, float>>
     */
    private function trendByMunicipality(Collection $delineations, Collection $areas): array
    {
        if ($delineations->isEmpty()) {
            return [];
        }

        $firstYear = $this->firstYear($delineations);

        return $delineations
            ->groupBy(fn (Delineation $d) => self::municipalityName($d->features ?? []) ?? '')
            ->forget('')
            ->sortKeys(SORT_NATURAL | SORT_FLAG_CASE)
            ->map(fn (Collection $group) => $this->trend($group, $areas, $firstYear))
            ->all();
    }

    private function firstYear(Collection $delineations): int
    {
        $currentYear = now()->year;

        return min($currentYear, (int) $delineations->min(fn (Delineation $d) => $d->created_at?->year ?? $currentYear));
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
    private function trend(Collection $delineations, Collection $areas, ?int $firstYear = null): array
    {
        if ($delineations->isEmpty()) {
            return [];
        }

        $currentYear = now()->year;
        $firstYear ??= $this->firstYear($delineations);

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
