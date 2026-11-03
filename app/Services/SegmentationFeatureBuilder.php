<?php

namespace App\Services;

/**
 * Converts polygonized segmentation output (GeoJSON, EPSG:4326) into the
 * delineation feature shape used by the map: ['type' => 'area', 'coords' => [[lat, lng], ...]].
 *
 * Only outer rings are kept (delineation areas have no holes), specks below the
 * minimum area are dropped, and the largest polygons are returned first.
 */
class SegmentationFeatureBuilder
{
    private const METRES_PER_DEGREE_LAT = 110540.0;

    private const METRES_PER_DEGREE_LNG = 111320.0;

    /**
     * @param  array<string, mixed>  $geoJson
     * @return array{features: array<int, array<string, mixed>>, polygon_count: int, kept_area_m2: float}
     */
    public function build(array $geoJson, float $minAreaM2, int $maxFeatures, int $maxVertices): array
    {
        $polygons = [];

        foreach ($geoJson['features'] ?? [] as $feature) {
            $geometry = $feature['geometry'] ?? null;
            $rings = match ($geometry['type'] ?? null) {
                'Polygon' => [$geometry['coordinates'][0] ?? []],
                'MultiPolygon' => array_map(fn ($polygon) => $polygon[0] ?? [], $geometry['coordinates'] ?? []),
                default => [],
            };

            foreach ($rings as $ring) {
                $ring = $this->cleanRing($ring);
                if (count($ring) < 4) {
                    continue;
                }

                $area = $this->ringAreaM2($ring);
                if ($area >= $minAreaM2) {
                    $polygons[] = ['ring' => $ring, 'area' => $area];
                }
            }
        }

        usort($polygons, fn ($a, $b) => $b['area'] <=> $a['area']);
        $polygonCount = count($polygons);
        $polygons = array_slice($polygons, 0, max(0, $maxFeatures));

        $features = array_map(fn (array $polygon) => [
            'type' => 'area',
            // GeoJSON is [lng, lat]; the map stores Leaflet order [lat, lng].
            'coords' => array_map(
                fn (array $point) => [round($point[1], 7), round($point[0], 7)],
                $this->limitVertices($polygon['ring'], $maxVertices)
            ),
            'area_m2' => round($polygon['area'], 1),
            'genera' => [],
        ], $polygons);

        return [
            'features' => $features,
            'polygon_count' => $polygonCount,
            'kept_area_m2' => round(array_sum(array_column($polygons, 'area')), 1),
        ];
    }

    /**
     * @param  array<int, mixed>  $ring
     * @return array<int, array{0: float, 1: float}>
     */
    private function cleanRing(array $ring): array
    {
        $clean = [];
        foreach ($ring as $point) {
            if (! is_array($point) || ! isset($point[0], $point[1]) || ! is_numeric($point[0]) || ! is_numeric($point[1])) {
                continue;
            }
            $point = [(float) $point[0], (float) $point[1]];
            if ($clean !== [] && end($clean) === $point) {
                continue;
            }
            $clean[] = $point;
        }

        if ($clean !== [] && $clean[0] !== end($clean)) {
            $clean[] = $clean[0];
        }

        return $clean;
    }

    /** Planar shoelace area on a local equirectangular projection; accurate enough at stand scale. */
    private function ringAreaM2(array $ring): float
    {
        $latitudes = array_column($ring, 1);
        $lngScale = self::METRES_PER_DEGREE_LNG * cos(deg2rad(array_sum($latitudes) / count($latitudes)));
        $sum = 0.0;

        for ($i = 0, $n = count($ring) - 1; $i < $n; $i++) {
            $sum += ($ring[$i][0] * $lngScale) * ($ring[$i + 1][1] * self::METRES_PER_DEGREE_LAT)
                - ($ring[$i + 1][0] * $lngScale) * ($ring[$i][1] * self::METRES_PER_DEGREE_LAT);
        }

        return abs($sum) / 2;
    }

    /** Evenly thin a closed ring so the vertex editor and validation limits stay workable. */
    private function limitVertices(array $ring, int $maxVertices): array
    {
        $maxVertices = max(4, $maxVertices);
        if (count($ring) <= $maxVertices) {
            return $ring;
        }

        $open = array_slice($ring, 0, -1);
        $step = count($open) / ($maxVertices - 1);
        $thinned = [];
        for ($i = 0; $i < $maxVertices - 1; $i++) {
            $thinned[] = $open[(int) floor($i * $step)];
        }
        $thinned[] = $thinned[0];

        return $thinned;
    }
}
