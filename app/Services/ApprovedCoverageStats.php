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
     *     genera_ha: array<string, float>,
     *     trend: array<int, float>,
     *     trend_by_municipality: array<string, array<int, float>>,
     *     by_municipality: array<int, array{name: ?string, area_count: int, area_ha: float, contributors: int, genera: array<string, int>, genera_ha: array<string, float>, last_approved_at: ?string}>,
     * }
     */
    public function summary(): array
    {
        // Expert-approved areas only: never pending ones, and never one also marked rejected.
        // An approved drone re-survey is counted once, on the area it re-surveyed (its boundary was copied there).
        $delineations = Delineation::approved()
            ->where('is_rejected', false)
            ->withoutAppliedReassessments()
            ->with(['annualObservations:id,delineation_id,year,coverage_area_ha', 'user:id,name'])
            ->get(['id', 'user_id', 'name', 'features', 'created_at', 'approved_at']);

        $areas = $delineations->mapWithKeys(fn (Delineation $d) => [$d->id => self::areaHectares($d->features ?? [])]);

        return [
            'area_ha' => round($areas->sum(), 2),
            'area_count' => $delineations->count(),
            'genera' => $this->generaCounts($delineations),
            'genera_ha' => $this->generaHectares($delineations),
            'trend' => $this->trend($delineations, $areas),
            'trend_by_municipality' => $this->trendByMunicipality($delineations, $areas),
            'by_municipality' => $this->byMunicipality($delineations, $areas),
        ];
    }

    /**
     * The dashboard's report: approved areas, hectares, contributors and genera for each municipality,
     * largest first, each with its approved areas one by one (largest first). Areas not matched to a
     * barangay come last, as one row with a null name.
     *
     * @return array<int, array{name: ?string, area_count: int, area_ha: float, contributors: int, genera: array<string, int>, genera_ha: array<string, float>, last_approved_at: ?string, areas: array<int, array{id: int, name: ?string, barangays: array<int, string>, area_ha: float, genera: array<int, string>, mapped_by: ?string, approved_at: ?string}>}>
     */
    private function byMunicipality(Collection $delineations, Collection $areas): array
    {
        $rows = $delineations
            ->groupBy(fn (Delineation $d) => self::municipalityName($d->features ?? []) ?? '')
            ->map(fn (Collection $group, string $name) => [
                'name' => $name === '' ? null : $name,
                'area_count' => $group->count(),
                'area_ha' => round($group->sum(fn (Delineation $d) => $areas[$d->id]), 2),
                'contributors' => $group->pluck('user_id')->unique()->count(),
                'genera' => $this->generaCounts($group),
                'genera_ha' => $this->generaHectares($group),
                'last_approved_at' => $group->max(fn (Delineation $d) => $d->approved_at ?? $d->created_at)?->toIso8601String(),
                'areas' => $group
                    ->map(fn (Delineation $d) => [
                        'id' => $d->id,
                        'name' => $d->name,
                        'barangays' => self::barangayNames($d->features ?? []),
                        'area_ha' => round($areas[$d->id], 2),
                        'genera' => DelineationObservationRecorder::generaPresent(array_filter($d->features ?? [], 'is_array')),
                        'mapped_by' => $d->user?->name,
                        'approved_at' => ($d->approved_at ?? $d->created_at)?->toIso8601String(),
                    ])
                    ->sortByDesc('area_ha')
                    ->values()
                    ->all(),
            ])
            ->values();

        return $rows
            ->sortBy([
                fn (array $a, array $b) => ($a['name'] === null) <=> ($b['name'] === null),
                fn (array $a, array $b) => $b['area_ha'] <=> $a['area_ha'],
                fn (array $a, array $b) => strnatcasecmp((string) $a['name'], (string) $b['name']),
            ])
            ->values()
            ->all();
    }

    /**
     * The barangays a delineation's features were matched to, in order, without repeats.
     *
     * @return array<int, string>
     */
    public static function barangayNames(array $features): array
    {
        return collect($features)
            ->map(fn ($feature) => is_array($feature) ? ($feature['location_details']['barangay']['name'] ?? null) : null)
            ->filter(fn ($name) => is_string($name) && trim($name) !== '')
            ->map(fn (string $name) => trim($name))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Hectares of approved area where each genus was recorded, largest first. Each drawn area counts
     * for every genus recorded in it, so an area with two genera is counted under both.
     *
     * @return array<string, float>
     */
    private function generaHectares(Collection $delineations): array
    {
        $hectares = [];
        foreach ($delineations as $delineation) {
            foreach (array_filter($delineation->features ?? [], 'is_array') as $feature) {
                $genera = DelineationObservationRecorder::generaPresent([$feature]);
                if ($genera === [] || ! isset($feature['coords'])) {
                    continue;
                }
                $area = self::areaHectares([$feature]);
                foreach ($genera as $genus) {
                    $hectares[$genus] = ($hectares[$genus] ?? 0) + $area;
                }
            }
        }
        arsort($hectares);

        return array_map(fn (float $ha) => round($ha, 2), $hectares);
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
