<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The bounding box of each municipality, from the imported barangay boundaries (admin_boundaries),
 * so the map's year slider can zoom to the municipality picked in its dropdown.
 * Empty on SQLite or when no boundaries are imported (like BoundaryResolver, it then does nothing).
 */
class MunicipalityBounds
{
    private const CACHE_KEY = 'map.municipality-bounds.v1';

    /**
     * @return array<string, array{0: float, 1: float, 2: float, 3: float}>  name => [south, west, north, east]
     */
    public function all(): array
    {
        $cached = Cache::get(self::CACHE_KEY);
        if (is_array($cached)) {
            return $cached;
        }

        try {
            if (DB::connection()->getDriverName() !== 'mysql') {
                return [];
            }
            $bounds = $this->compute();
        } catch (Throwable) {
            return [];
        }

        // Nothing imported yet: look again next time instead of remembering an empty list.
        if ($bounds !== []) {
            Cache::put(self::CACHE_KEY, $bounds, now()->addDay());
        }

        return $bounds;
    }

    /** Forget the stored boxes (after the boundaries are imported again). */
    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function compute(): array
    {
        $bounds = [];
        // MySQL has no ST_Envelope for latitude/longitude shapes, so the corners are found here.
        $rows = DB::table('admin_boundaries')
            ->whereNotNull('adm3_name')
            ->selectRaw('adm3_name, ST_AsGeoJSON(geom) AS shape')
            ->cursor();

        foreach ($rows as $row) {
            $coordinates = json_decode((string) $row->shape, true)['coordinates'] ?? null;
            if (! is_array($coordinates)) {
                continue;
            }

            [$south, $west, $north, $east] = $bounds[$row->adm3_name] ?? [90.0, 180.0, -90.0, -180.0];
            // GeoJSON positions are [longitude, latitude], so the numbers alternate.
            $isLongitude = true;
            array_walk_recursive($coordinates, function ($value) use (&$south, &$west, &$north, &$east, &$isLongitude) {
                $value = (float) $value;
                if ($isLongitude) {
                    $west = min($west, $value);
                    $east = max($east, $value);
                } else {
                    $south = min($south, $value);
                    $north = max($north, $value);
                }
                $isLongitude = ! $isLongitude;
            });
            $bounds[$row->adm3_name] = [$south, $west, $north, $east];
        }

        ksort($bounds, SORT_NATURAL | SORT_FLAG_CASE);

        return array_map(fn (array $box) => array_map(fn (float $value) => round($value, 5), $box), $bounds);
    }
}
