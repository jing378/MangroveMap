<?php

namespace App\Services;

use Throwable;

/**
 * Checks that the GeoTIFFs of one drone imagery batch belong to the same site. A batch becomes a single
 * mosaic covering the box around every file, so files from different places (e.g. Macrohon and Bontoc)
 * would make a huge, mostly empty raster and one delineation spanning both towns.
 */
class DroneBatchFootprints
{
    public function __construct(private BoundaryResolver $boundaries) {}

    /**
     * Groups files whose footprints are within $maxGapMetres of each other (directly or through other files).
     *
     * @param  array<string, array{west: float, south: float, east: float, north: float}>  $footprints  WGS84 bounds by file name
     * @return array<int, array<int, string>> file names per group, largest group first
     */
    public function group(array $footprints, float $maxGapMetres): array
    {
        $names = array_keys($footprints);
        $parent = array_combine($names, $names);
        $find = function (string $name) use (&$parent): string {
            while ($parent[$name] !== $name) {
                $parent[$name] = $parent[$parent[$name]];
                $name = $parent[$name];
            }

            return $name;
        };

        foreach ($names as $i => $first) {
            foreach (array_slice($names, $i + 1) as $second) {
                if ($this->gapMetres($footprints[$first], $footprints[$second]) <= $maxGapMetres) {
                    $parent[$find($first)] = $find($second);
                }
            }
        }

        $groups = [];
        foreach ($names as $name) {
            $groups[$find($name)][] = $name;
        }
        $groups = array_values($groups);
        usort($groups, fn (array $a, array $b) => count($b) <=> count($a));

        return $groups;
    }

    /** Shortest ground distance between two bounding boxes (0 when they touch or overlap). */
    public function gapMetres(array $a, array $b): float
    {
        $lngGap = max(0, $a['west'] - $b['east'], $b['west'] - $a['east']);
        $latGap = max(0, $a['south'] - $b['north'], $b['south'] - $a['north']);
        $latitude = deg2rad(($a['south'] + $a['north'] + $b['south'] + $b['north']) / 4);

        return hypot($lngGap * 111320 * cos($latitude), $latGap * 110574);
    }

    /**
     * Error message for a batch with more than one group, naming the municipality of each when the
     * barangay boundaries are loaded.
     *
     * @param  array<int, array<int, string>>  $groups
     * @param  array<string, array{west: float, south: float, east: float, north: float}>  $footprints
     */
    public function farApartMessage(array $groups, array $footprints): string
    {
        $parts = [];
        $boxes = [];
        foreach ($groups as $group) {
            $box = $this->union(array_map(fn (string $name) => $footprints[$name], $group));
            $boxes[] = $box;
            $place = $this->placeName($box);
            $files = implode(', ', array_slice($group, 0, 3)).(count($group) > 3 ? ' and '.(count($group) - 3).' more' : '');
            $parts[] = $place !== null ? "{$place} ({$files})" : $files;
        }

        $distanceKm = $this->gapMetres($boxes[0], $boxes[1]) / 1000;
        $distance = $distanceKm >= 1 ? number_format($distanceKm, 1).' km' : round($distanceKm * 1000).' m';

        return 'These files are from places far apart and cannot be combined into one drone image: '
            .implode('; ', $parts).". The first two are about {$distance} apart. "
            .'Upload the files of each place as a separate batch.';
    }

    /** @param  array<int, array{west: float, south: float, east: float, north: float}>  $boxes */
    private function union(array $boxes): array
    {
        return [
            'west' => min(array_column($boxes, 'west')),
            'south' => min(array_column($boxes, 'south')),
            'east' => max(array_column($boxes, 'east')),
            'north' => max(array_column($boxes, 'north')),
        ];
    }

    private function placeName(array $box): ?string
    {
        try {
            $feature = $this->boundaries->resolveFeatures([[
                'type' => 'point',
                'coords' => [($box['south'] + $box['north']) / 2, ($box['west'] + $box['east']) / 2],
            ]])[0];

            return $feature['location_details']['municipality']['name'] ?? null;
        } catch (Throwable) {
            return null;
        }
    }
}
