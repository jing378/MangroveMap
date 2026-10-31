<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use JsonException;
use RuntimeException;
use Throwable;

class ImportAdminBoundaries extends Command
{
    protected $signature = 'boundaries:import {--path= : GeoJSON file to import} {--accept-qgis-valid : Trust a successful QGIS GEOS validity check when MySQL disagrees}';

    protected $description = 'Import Southern Leyte barangay boundaries into the spatial lookup table';

    public function handle(): int
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->error('Boundary import requires the configured MySQL database.');

            return self::FAILURE;
        }

        $path = $this->option('path') ?: storage_path('app/geo/southern_leyte_barangay_boundaries.geojson');
        if (! is_file($path)) {
            $this->error("Boundary GeoJSON not found: {$path}");

            return self::FAILURE;
        }

        try {
            $collection = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $this->error('Boundary GeoJSON is invalid: ' . $exception->getMessage());

            return self::FAILURE;
        }

        $features = $collection['features'] ?? null;
        if (($collection['type'] ?? null) !== 'FeatureCollection' || ! is_array($features) || $features === []) {
            $this->error('Expected a non-empty GeoJSON FeatureCollection.');

            return self::FAILURE;
        }

        $crsName = strtolower((string) data_get($collection, 'crs.properties.name', ''));
        if ($crsName !== '' && ! str_contains($crsName, 'crs84') && ! str_contains($crsName, '4326')) {
            $this->error("Expected GeoJSON in CRS84/EPSG:4326, received {$crsName}.");

            return self::FAILURE;
        }

        try {
            DB::transaction(function () use ($features): void {
                DB::table('admin_boundaries')->delete();

                foreach ($features as $index => $feature) {
                    $properties = $feature['properties'] ?? [];
                    $geometry = $feature['geometry'] ?? null;
                    $requiredProperties = [
                        'adm4_pcode',
                        'adm4_name',
                        'adm3_pcode',
                        'adm3_name',
                        'adm2_pcode',
                        'adm2_name',
                        'version',
                    ];

                    foreach ($requiredProperties as $property) {
                        if (empty($properties[$property])) {
                            throw new RuntimeException("Feature {$index} is missing {$property}.");
                        }
                    }

                    if ($properties['adm2_pcode'] !== 'PH08064') {
                        throw new RuntimeException("Feature {$index} is not in Southern Leyte.");
                    }

                    if (($geometry['type'] ?? null) !== 'MultiPolygon') {
                        throw new RuntimeException("Feature {$index} is not a MultiPolygon.");
                    }

                    $geometryJson = json_encode($geometry, JSON_THROW_ON_ERROR);
                    $valid = DB::selectOne(
                        'SELECT ST_IsValid(ST_GeomFromGeoJSON(?, 1, 4326)) AS is_valid',
                        [$geometryJson],
                    );

                    if (! (bool) $valid->is_valid) {
                        if (! $this->option('accept-qgis-valid')) {
                            throw new RuntimeException("Boundary {$properties['adm4_pcode']} is invalid to MySQL. Run QGIS Check validity first; use --accept-qgis-valid only when it reports zero invalid features.");
                        }

                        $this->warn("MySQL flags {$properties['adm4_pcode']} as invalid; accepting it based on the verified QGIS GEOS check.");
                    }

                    DB::statement(
                        'INSERT INTO admin_boundaries '
                            . '(adm4_pcode, adm4_name, adm3_pcode, adm3_name, adm2_pcode, adm2_name, dataset_version, dataset_valid_on, geom) '
                            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ST_GeomFromGeoJSON(?, 1, 4326))',
                        [
                            $properties['adm4_pcode'],
                            $properties['adm4_name'],
                            $properties['adm3_pcode'],
                            $properties['adm3_name'],
                            $properties['adm2_pcode'],
                            $properties['adm2_name'],
                            $properties['version'],
                            $properties['valid_on'] ?? null,
                            $geometryJson,
                        ],
                    );
                }
            });
        } catch (Throwable $exception) {
            $this->error('Boundary import failed; existing data was preserved. ' . $exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Imported ' . count($features) . ' Southern Leyte barangay boundaries.');

        return self::SUCCESS;
    }
}
