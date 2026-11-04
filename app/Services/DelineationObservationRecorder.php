<?php

namespace App\Services;

use App\Models\Delineation;
use App\Models\DelineationAnnualObservation;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Turns an expert's boundary edit into that year's annual observation, so the
 * "Changes over time" timeline and coverage trend follow the map edits.
 */
class DelineationObservationRecorder
{
    /** Same mean earth radius as calculateFeatureAreaHectares() on the map, so both show the same hectares. */
    private const EARTH_RADIUS_METERS = 6371008.8;

    /**
     * Must run inside the transaction that updates the boundary.
     *
     * @param  array<int, array{type: string, coords: array, genera?: array}>  $previousFeatures  boundary before the edit
     * @param  array<int, array{type: string, coords: array, genera?: array}>  $features  boundary after the edit
     */
    public function record(
        Delineation $delineation,
        array $previousFeatures,
        array $features,
        CarbonInterface $observedOn,
        string $source,
        User $expert,
    ): DelineationAnnualObservation {
        $year = $observedOn->year;

        // The first observed change also stores the boundary as it was first drawn, so the
        // timeline has a "before" to compare with. Skipped when both fall in the same year.
        $baselineYear = $delineation->created_at?->year;
        if ($baselineYear && $baselineYear < $year && ! $delineation->annualObservations()->exists()) {
            $this->saveYear($delineation, $baselineYear, $delineation->created_at, $previousFeatures, DelineationAnnualObservation::BASELINE_SOURCE, $expert);
        }

        return $this->saveYear($delineation, $year, $observedOn, $features, $source, $expert);
    }

    /**
     * @param  array<int, array{type: string, coords: array}>  $features
     */
    public static function areaHectares(array $features): float
    {
        $total = 0.0;
        foreach ($features as $feature) {
            if (($feature['type'] ?? null) !== 'area' || ! is_array($feature['coords'] ?? null) || count($feature['coords']) < 3) {
                continue;
            }

            $points = array_map(fn(array $pair) => [deg2rad((float) $pair[0]), deg2rad((float) $pair[1])], $feature['coords']);
            $area = 0.0;
            for ($i = 0, $n = count($points); $i < $n; $i++) {
                [$lat1, $lng1] = $points[$i];
                [$lat2, $lng2] = $points[($i + 1) % $n];
                $area += ($lng2 - $lng1) * (2 + sin($lat1) + sin($lat2));
            }

            $total += abs($area * self::EARTH_RADIUS_METERS ** 2 / 2) / 10000;
        }

        return round($total, 4);
    }

    /**
     * @param  array<int, array{genera?: array}>  $features
     * @return array<int, string>
     */
    public static function generaPresent(array $features): array
    {
        return collect($features)
            ->flatMap(fn(array $feature) => is_array($feature['genera'] ?? null) ? $feature['genera'] : [])
            ->map(fn($genus) => trim((string) $genus))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function saveYear(
        Delineation $delineation,
        int $year,
        CarbonInterface $observedOn,
        array $features,
        string $source,
        User $expert,
    ): DelineationAnnualObservation {
        // One observation per zone and year; a later edit in the same year replaces the figures.
        $observation = $delineation->annualObservations()->firstOrNew(['year' => $year]);
        $observation->fill([
            'observation_date' => $observedOn->toDateString(),
            'coverage_area_ha' => self::areaHectares($features),
            'genera_present' => self::generaPresent($features) ?: null,
            'source' => $source,
        ]);
        $observation->created_by ??= $expert->id;
        $observation->save();

        $observation->boundaryRevisions()->create([
            // reorder(): the relation is ordered by version, which MySQL rejects next to MAX().
            'version' => ((int) $observation->boundaryRevisions()->reorder()->max('version')) + 1,
            'features' => $features,
            'created_by' => $expert->id,
        ]);

        return $observation;
    }
}
