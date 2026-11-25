<?php

namespace App\Support;

/**
 * Measures how two sets of mangrove areas overlap (an AI draft against a reference boundary), in hectares,
 * with the usual segmentation scores: IoU, precision, recall and F1.
 *
 * Plain PHP, so it runs anywhere (tests on SQLite, no GDAL or Python): the areas are projected to metres
 * around their mean latitude and swept with horizontal scanlines, which gives the covered length on each
 * line as intervals; overlaps are interval intersections. With 0.1 m lines the result is well within the
 * accuracy of any drawn or detected boundary.
 *
 * A "shape" is a list of rings (outer ring and holes, filled even-odd); a set of shapes is their union, so
 * overlapping areas are counted once. Rings are lists of [x, y] points ([lng, lat] for geographic input).
 */
class ExtentOverlap
{
    public const METRES_PER_DEGREE = 111319.49;

    /** Scanlines are spaced at least this far apart, at most MAX_LINES lines per comparison. */
    public const DEFAULT_STEP_M = 0.1;

    private const MAX_LINES = 40000;

    /**
     * @param  array<int, array<int, array<int, array{0: float, 1: float}>>>  $predicted  shapes in [lng, lat]
     * @param  array<int, array<int, array<int, array{0: float, 1: float}>>>  $reference  shapes in [lng, lat]
     * @return array{predicted_ha: float, reference_ha: float, overlap_ha: float, union_ha: float, iou: ?float, precision: ?float, recall: ?float, f1: ?float}
     */
    public static function compare(array $predicted, array $reference, float $stepM = self::DEFAULT_STEP_M): array
    {
        $latitudes = [];
        foreach ([$predicted, $reference] as $shapes) {
            foreach ($shapes as $shape) {
                foreach ($shape as $ring) {
                    foreach ($ring as $point) {
                        $latitudes[] = (float) $point[1];
                    }
                }
            }
        }
        if ($latitudes === []) {
            return self::scores(0.0, 0.0, 0.0);
        }

        $originLat = array_sum($latitudes) / count($latitudes);
        $project = self::projector($originLat);
        $predictedM = self::mapShapes($predicted, $project);
        $referenceM = self::mapShapes($reference, $project);

        $predictedEdges = self::edges($predictedM);
        $referenceEdges = self::edges($referenceM);
        $bounds = self::yBounds([...$predictedEdges, ...$referenceEdges]);
        if ($bounds === null) {
            return self::scores(0.0, 0.0, 0.0);
        }
        [$minY, $maxY] = $bounds;
        $step = max($stepM, ($maxY - $minY) / self::MAX_LINES);

        $predictedM2 = $referenceM2 = $overlapM2 = 0.0;
        for ($y = $minY + $step / 2; $y < $maxY; $y += $step) {
            $p = self::intervalsAt($predictedEdges, $y);
            $r = self::intervalsAt($referenceEdges, $y);
            $predictedM2 += self::length($p) * $step;
            $referenceM2 += self::length($r) * $step;
            $overlapM2 += self::intersectionLength($p, $r) * $step;
        }

        return self::scores($predictedM2 / 10000, $referenceM2 / 10000, $overlapM2 / 10000);
    }

    /**
     * Shapes from stored delineation / AI-extent features: polygon features ("area", or no type) with
     * Leaflet [lat, lng] coords, one ring each.
     *
     * @return array<int, array<int, array<int, array{0: float, 1: float}>>>
     */
    public static function shapesFromFeatures(array $features): array
    {
        $shapes = [];
        foreach ($features as $feature) {
            if (! is_array($feature) || ! in_array($feature['type'] ?? 'area', ['area'], true)) {
                continue;
            }
            $ring = self::ring(array_map(
                fn ($point) => is_array($point) && count($point) >= 2 ? [(float) $point[1], (float) $point[0]] : null,
                is_array($feature['coords'] ?? null) ? $feature['coords'] : [],
            ));
            if ($ring !== null) {
                $shapes[] = [$ring];
            }
        }

        return $shapes;
    }

    /**
     * Shapes from GeoJSON (FeatureCollection, Feature, Polygon or MultiPolygon) in [lng, lat].
     *
     * @return array<int, array<int, array<int, array{0: float, 1: float}>>>
     */
    public static function shapesFromGeoJson(array $geoJson): array
    {
        $type = $geoJson['type'] ?? null;
        if ($type === 'FeatureCollection') {
            return array_merge([], ...array_map(
                fn ($feature) => is_array($feature) ? self::shapesFromGeoJson($feature) : [],
                (array) ($geoJson['features'] ?? []),
            ));
        }
        if ($type === 'Feature') {
            return is_array($geoJson['geometry'] ?? null) ? self::shapesFromGeoJson($geoJson['geometry']) : [];
        }
        if ($type === 'GeometryCollection') {
            return array_merge([], ...array_map(
                fn ($geometry) => is_array($geometry) ? self::shapesFromGeoJson($geometry) : [],
                (array) ($geoJson['geometries'] ?? []),
            ));
        }

        $polygons = match ($type) {
            'Polygon' => [(array) ($geoJson['coordinates'] ?? [])],
            'MultiPolygon' => (array) ($geoJson['coordinates'] ?? []),
            default => [],
        };
        $shapes = [];
        foreach ($polygons as $polygon) {
            $rings = [];
            foreach ((array) $polygon as $ring) {
                $ring = self::ring(array_map(
                    fn ($point) => is_array($point) && count($point) >= 2 ? [(float) $point[0], (float) $point[1]] : null,
                    (array) $ring,
                ));
                if ($ring !== null) {
                    $rings[] = $ring;
                }
            }
            if ($rings !== []) {
                $shapes[] = $rings;
            }
        }

        return $shapes;
    }

    /**
     * Edges of every ring, grouped by shape, for intervalsAt(): [shape index, x1, y1, x2, y2].
     *
     * @return array<int, array{0: int, 1: float, 2: float, 3: float, 4: float}>
     */
    public static function edges(array $shapes): array
    {
        $edges = [];
        foreach (array_values($shapes) as $index => $shape) {
            foreach ($shape as $ring) {
                $count = count($ring);
                for ($i = 0; $i < $count; $i++) {
                    [$x1, $y1] = $ring[$i];
                    [$x2, $y2] = $ring[($i + 1) % $count];
                    if ($y1 != $y2) {
                        $edges[] = [$index, (float) $x1, (float) $y1, (float) $x2, (float) $y2];
                    }
                }
            }
        }

        return $edges;
    }

    /**
     * Where the horizontal line at $y is inside the union of the shapes: sorted, non-overlapping [from, to] intervals.
     *
     * @param  array<int, array{0: int, 1: float, 2: float, 3: float, 4: float}>  $edges  from edges()
     * @return array<int, array{0: float, 1: float}>
     */
    public static function intervalsAt(array $edges, float $y): array
    {
        $crossings = [];
        foreach ($edges as [$shape, $x1, $y1, $x2, $y2]) {
            if (($y1 <= $y) !== ($y2 <= $y)) {
                $crossings[$shape][] = $x1 + ($y - $y1) * ($x2 - $x1) / ($y2 - $y1);
            }
        }

        $intervals = [];
        foreach ($crossings as $xs) {
            sort($xs);
            for ($i = 0; $i + 1 < count($xs); $i += 2) {
                $intervals[] = [$xs[$i], $xs[$i + 1]];
            }
        }

        return self::merge($intervals);
    }

    /** @param  array<int, array{0: float, 1: float}>  $intervals  sorted, non-overlapping */
    public static function length(array $intervals): float
    {
        $total = 0.0;
        foreach ($intervals as [$from, $to]) {
            $total += $to - $from;
        }

        return $total;
    }

    /**
     * Length covered by both interval lists (each sorted and non-overlapping).
     *
     * @param  array<int, array{0: float, 1: float}>  $a
     * @param  array<int, array{0: float, 1: float}>  $b
     */
    public static function intersectionLength(array $a, array $b): float
    {
        $total = 0.0;
        $i = $j = 0;
        while ($i < count($a) && $j < count($b)) {
            $from = max($a[$i][0], $b[$j][0]);
            $to = min($a[$i][1], $b[$j][1]);
            if ($to > $from) {
                $total += $to - $from;
            }
            if ($a[$i][1] < $b[$j][1]) {
                $i++;
            } else {
                $j++;
            }
        }

        return $total;
    }

    /**
     * @param  array<int, array{0: float, 1: float}>  $intervals
     * @return array<int, array{0: float, 1: float}>
     */
    public static function merge(array $intervals): array
    {
        if ($intervals === []) {
            return [];
        }
        usort($intervals, fn (array $a, array $b) => $a[0] <=> $b[0]);
        $merged = [$intervals[0]];
        foreach (array_slice($intervals, 1) as [$from, $to]) {
            $last = count($merged) - 1;
            if ($from <= $merged[$last][1]) {
                $merged[$last][1] = max($merged[$last][1], $to);
            } else {
                $merged[] = [$from, $to];
            }
        }

        return $merged;
    }

    /** @return array{0: float, 1: float}|null lowest and highest y of the edges */
    public static function yBounds(array $edges): ?array
    {
        if ($edges === []) {
            return null;
        }
        $minY = INF;
        $maxY = -INF;
        foreach ($edges as [, , $y1, , $y2]) {
            $minY = min($minY, $y1, $y2);
            $maxY = max($maxY, $y1, $y2);
        }

        return [$minY, $maxY];
    }

    /**
     * Scores from the three areas. Null when undefined (nothing drawn on either side).
     *
     * @return array{predicted_ha: float, reference_ha: float, overlap_ha: float, union_ha: float, iou: ?float, precision: ?float, recall: ?float, f1: ?float}
     */
    public static function scores(float $predictedHa, float $referenceHa, float $overlapHa): array
    {
        $overlapHa = min($overlapHa, $predictedHa, $referenceHa);
        $unionHa = $predictedHa + $referenceHa - $overlapHa;
        $sum = $predictedHa + $referenceHa;

        return [
            'predicted_ha' => round($predictedHa, 4),
            'reference_ha' => round($referenceHa, 4),
            'overlap_ha' => round($overlapHa, 4),
            'union_ha' => round($unionHa, 4),
            'iou' => $unionHa > 0 ? round($overlapHa / $unionHa, 4) : null,
            'precision' => $predictedHa > 0 ? round($overlapHa / $predictedHa, 4) : null,
            'recall' => $referenceHa > 0 ? round($overlapHa / $referenceHa, 4) : null,
            'f1' => $sum > 0 ? round(2 * $overlapHa / $sum, 4) : null,
        ];
    }

    /** A ring with at least 3 distinct points, without the repeated closing point, else null. */
    private static function ring(array $points): ?array
    {
        $points = array_values(array_filter($points, fn ($point) => is_array($point)
            && is_finite($point[0]) && is_finite($point[1])));
        if (count($points) > 1 && $points[0] == $points[count($points) - 1]) {
            array_pop($points);
        }

        return count($points) >= 3 ? $points : null;
    }

    /** [lng, lat] → metres east/north of the origin latitude's equator crossing (equirectangular). */
    private static function projector(float $originLat): \Closure
    {
        $cos = cos(deg2rad($originLat));

        return fn (array $point) => [
            $point[0] * $cos * self::METRES_PER_DEGREE,
            ($point[1] - $originLat) * self::METRES_PER_DEGREE,
        ];
    }

    private static function mapShapes(array $shapes, \Closure $project): array
    {
        return array_map(
            fn (array $shape) => array_map(fn (array $ring) => array_map($project, $ring), $shape),
            $shapes,
        );
    }
}
