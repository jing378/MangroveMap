<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class BoundaryResolver
{
    private const PROJECTED_SRID = 32651;

    /**
     * @param  array<int, array<string, mixed>>  $features
     * @return array<int, array<string, mixed>>
     */
    public function resolveFeatures(array $features): array
    {
        $lookupAvailable = $this->spatialLookupAvailable();

        foreach ($features as $index => $feature) {
            if (! $lookupAvailable) {
                $features[$index]['location'] = null;
                $features[$index]['location_status'] = 'pending';
                $features[$index]['location_details'] = null;
                continue;
            }

            $geometry = $this->geometryForFeature($feature);
            $resolved = $this->resolveGeometry($feature['type'], json_encode($geometry, JSON_THROW_ON_ERROR));

            $features[$index]['location'] = $resolved['location'] ?? null;
            $features[$index]['location_status'] = $resolved === null ? 'outside_boundaries' : 'matched';
            $features[$index]['location_details'] = $resolved['details'] ?? null;
        }

        return array_values($features);
    }

    /** @return array{type: string, coordinates: array} */
    public function geometryForFeature(array $feature): array
    {
        $type = $feature['type'] ?? null;
        $coords = $feature['coords'] ?? null;

        if ($type === 'point') {
            return ['type' => 'Point', 'coordinates' => $this->position($coords)];
        }

        if (! in_array($type, ['line', 'area'], true) || ! is_array($coords)) {
            throw ValidationException::withMessages(['features' => 'A valid point, line, or area is required.']);
        }

        $positions = array_map(fn($position) => $this->position($position), $coords);
        $minimumPositions = $type === 'line' ? 2 : 3;
        if (count($positions) < $minimumPositions) {
            throw ValidationException::withMessages(['features' => 'The boundary has too few coordinates.']);
        }

        if ($type === 'line') {
            return ['type' => 'LineString', 'coordinates' => $positions];
        }

        if (! $this->samePosition($positions[0], $positions[array_key_last($positions)])) {
            $positions[] = $positions[0];
        }

        return ['type' => 'Polygon', 'coordinates' => [$positions]];
    }

    private function position(mixed $position): array
    {
        if (
            ! is_array($position) || ! array_is_list($position) || count($position) !== 2
            || ! is_numeric($position[0]) || ! is_numeric($position[1])
        ) {
            throw ValidationException::withMessages(['features' => 'Coordinates must be [latitude, longitude] pairs.']);
        }

        $latitude = (float) $position[0];
        $longitude = (float) $position[1];
        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            throw ValidationException::withMessages(['features' => 'Coordinates are outside valid latitude/longitude ranges.']);
        }

        return [$longitude, $latitude];
    }

    private function samePosition(array $first, array $second): bool
    {
        return abs($first[0] - $second[0]) < 1e-10 && abs($first[1] - $second[1]) < 1e-10;
    }

    private function spatialLookupAvailable(): bool
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return false;
        }

        return Schema::hasTable('admin_boundaries') && DB::table('admin_boundaries')->exists();
    }

    private function resolveGeometry(string $type, string $geoJson): ?array
    {
        $valid = DB::selectOne(
            'SELECT ST_IsValid(ST_GeomFromGeoJSON(?, 1, 4326)) AS is_valid',
            [$geoJson],
        );
        if (! (bool) $valid->is_valid) {
            throw ValidationException::withMessages(['features' => 'A delineation has invalid geometry. Check that its area does not cross over itself.']);
        }

        $projectedSrid = self::PROJECTED_SRID;
        $sourceGeometry = 'ST_GeomFromGeoJSON(?, 1, 4326)';
        $projectedFeature = "ST_Transform({$sourceGeometry}, {$projectedSrid})";
        $projectedBoundary = "ST_Transform(boundary.geom, {$projectedSrid})";
        $inputShape = "CROSS JOIN (SELECT {$sourceGeometry} AS geom, {$projectedFeature} AS projected_geom) AS input_shape";
        $bindings = [$geoJson, $geoJson];

        if ($type === 'point') {
            $sql = "SELECT boundary.adm4_pcode, boundary.adm4_name, boundary.adm3_pcode, boundary.adm3_name, "
                . "boundary.adm2_pcode, boundary.adm2_name, boundary.dataset_version, boundary.dataset_valid_on, "
                . "ST_Contains({$projectedBoundary}, input_shape.projected_geom) AS contains_point "
                . "FROM admin_boundaries AS boundary {$inputShape} "
                . 'WHERE MBRIntersects(boundary.geom, input_shape.geom) '
                . "AND ST_Intersects({$projectedBoundary}, input_shape.projected_geom) "
                . 'ORDER BY contains_point DESC, boundary.adm4_pcode ASC LIMIT 1';
        } else {
            $measure = $type === 'line' ? 'ST_Length' : 'ST_Area';
            $intersectionMeasure = "{$measure}(ST_Intersection({$projectedBoundary}, input_shape.projected_geom))";
            $inputMeasure = "{$measure}(input_shape.projected_geom)";
            $inputMeasureResult = DB::selectOne(
                "SELECT {$inputMeasure} AS measure FROM (SELECT {$projectedFeature} AS projected_geom) AS input_shape",
                [$geoJson],
            );
            if ((float) $inputMeasureResult->measure <= 0) {
                throw ValidationException::withMessages(['features' => 'A delineation must have non-zero length or area.']);
            }

            $sql = "SELECT boundary.adm4_pcode, boundary.adm4_name, boundary.adm3_pcode, boundary.adm3_name, "
                . "boundary.adm2_pcode, boundary.adm2_name, boundary.dataset_version, boundary.dataset_valid_on, "
                . "{$intersectionMeasure} AS overlap_measure, {$inputMeasure} AS input_measure "
                . "FROM admin_boundaries AS boundary {$inputShape} "
                . 'WHERE MBRIntersects(boundary.geom, input_shape.geom) '
                . "AND ST_Intersects({$projectedBoundary}, input_shape.projected_geom) "
                . "AND {$intersectionMeasure} > 0 "
                . 'ORDER BY overlap_measure DESC, boundary.adm4_pcode ASC';
        }

        $matches = DB::select($sql, $bindings);
        if ($matches === []) {
            return null;
        }

        $dominant = $matches[0];
        $locations = array_map(function ($match) use ($type) {
            $overlapPercent = $type === 'point'
                ? 100.0
                : ($match->input_measure > 0 ? 100 * $match->overlap_measure / $match->input_measure : 0.0);

            return [
                'barangay' => ['code' => $match->adm4_pcode, 'name' => $match->adm4_name],
                'municipality' => ['code' => $match->adm3_pcode, 'name' => $match->adm3_name],
                'province' => ['code' => $match->adm2_pcode, 'name' => $match->adm2_name],
                'overlap_percent' => round(min(100, max(0, $overlapPercent)), 2),
                'dataset_version' => $match->dataset_version,
                'dataset_valid_on' => $match->dataset_valid_on,
            ];
        }, $matches);

        return [
            'location' => implode(', ', array_unique([
                $dominant->adm4_name,
                $dominant->adm3_name,
                $dominant->adm2_name,
            ])),
            'details' => [
                ...$locations[0],
                'matches' => $locations,
            ],
        ];
    }
}
