<?php

namespace App\Services;

use RuntimeException;

/**
 * Runs the mangrove U-Net (MangroveSegmentationService::predictRaster) over any georeferenced RGB raster
 * and returns the detected mangrove areas as GeoJSON polygons in EPSG:4326.
 *
 * Same steps as SegmentDroneImagery, for rasters in any coordinate system: a working copy is warped to
 * EPSG:3857 at the model's ground resolution (ml.drone.resolution, capped by ml.drone.max_dimension),
 * predicted, and the mask is vectorized and simplified. Used by the Tile Explorer to label tiles.
 */
class MangroveAreaDetector
{
    private const EARTH_RADIUS = 6378137.0;

    public function __construct(
        private GdalCommandRunner $gdal,
        private MangroveSegmentationService $segmentation,
    ) {}

    /**
     * @param  array  $info  gdalinfo -json of $rasterPath
     * @return array{geojson: array, coverage_percent: float}
     */
    public function detect(string $rasterPath, array $info, string $workDirectory): array
    {
        $timeout = (int) config('gdal.timeout');
        $bandsPath = $workDirectory.DIRECTORY_SEPARATOR.'detect_bands.vrt';
        $inputPath = $workDirectory.DIRECTORY_SEPARATOR.'detect_input.tif';
        $maskPath = $workDirectory.DIRECTORY_SEPARATOR.'detect_mask.png';
        $maskGeoPath = $workDirectory.DIRECTORY_SEPARATOR.'detect_mask_geo.tif';
        $polygonsPath = $workDirectory.DIRECTORY_SEPARATOR.'detect_polygons.gpkg';
        $geoJsonPath = $workDirectory.DIRECTORY_SEPARATOR.'detect_areas.geojson';

        [$west, $south, $east, $north] = $this->wgs84Extent($info);
        $mercatorScale = 1 / max(0.01, cos(deg2rad(($south + $north) / 2)));
        $longestSide = max(
            self::EARTH_RADIUS * deg2rad($east - $west),
            $this->mercatorY($north) - $this->mercatorY($south),
        );
        $pixelSize = max(
            (float) config('ml.drone.resolution', 0.15) * $mercatorScale,
            $longestSide / max(512, (int) config('ml.drone.max_dimension', 12000)),
        );

        // RGB (+ alpha) only, then into EPSG:3857 at the model resolution; outside the image becomes transparent.
        $this->gdal->run(['gdal_translate', '-q', '-of', 'VRT', ...$this->rgbBandArguments($info), $rasterPath, $bandsPath], $workDirectory, $timeout);
        $this->gdal->run([
            'gdalwarp', '-q', '-overwrite',
            '-t_srs', 'EPSG:3857',
            '-tr', $this->number($pixelSize), $this->number($pixelSize),
            '-r', 'average',
            '-dstalpha',
            '-co', 'COMPRESS=NONE',
            '-co', 'INTERLEAVE=PIXEL',
            $bandsPath,
            $inputPath,
        ], $workDirectory, $timeout);

        $inputInfo = json_decode($this->gdal->run(['gdalinfo', '-json', $inputPath], $workDirectory, $timeout), true);
        if (! is_array($inputInfo) || empty($inputInfo['geoTransform']) || empty($inputInfo['size'])) {
            throw new RuntimeException('GDAL could not prepare the image for mangrove detection.');
        }

        $prediction = $this->segmentation->predictRaster($inputPath, $maskPath);

        $this->gdal->run([
            'gdal_translate', '-q',
            '-a_srs', 'EPSG:3857',
            '-a_ullr', ...array_map($this->number(...), $this->cornerCoordinates($inputInfo)),
            '-a_nodata', '0',
            $maskPath,
            $maskGeoPath,
        ], $workDirectory, $timeout);
        $this->gdal->run([
            'python', '-m', 'osgeo_utils.gdal_polygonize', '-q', '-overwrite',
            $maskGeoPath, '-b', '1', '-f', 'GPKG', $polygonsPath, 'areas', 'DN',
        ], $workDirectory, $timeout);
        $this->gdal->run([
            'ogr2ogr', '-overwrite',
            '-f', 'GeoJSON',
            '-t_srs', 'EPSG:4326',
            '-simplify', $this->number((float) config('ml.drone.simplify_metres', 1.0) * $mercatorScale),
            '-lco', 'COORDINATE_PRECISION=7',
            '-lco', 'RFC7946=YES',
            $geoJsonPath,
            $polygonsPath,
            'areas',
        ], $workDirectory, $timeout);

        $geoJson = json_decode((string) @file_get_contents($geoJsonPath), true);
        if (! is_array($geoJson) || ! is_array($geoJson['features'] ?? null)) {
            throw new RuntimeException('Vectorizing the mangrove mask produced invalid GeoJSON.');
        }

        return [
            'geojson' => $geoJson,
            'coverage_percent' => (float) ($prediction['mangrove_coverage_percent'] ?? 0),
        ];
    }

    /** @return array{0: float, 1: float, 2: float, 3: float} west, south, east, north */
    private function wgs84Extent(array $info): array
    {
        $coordinates = $info['wgs84Extent']['coordinates'][0] ?? [];
        $longitudes = array_map(fn ($point) => (float) $point[0], $coordinates);
        $latitudes = array_map(fn ($point) => (float) $point[1], $coordinates);
        if ($longitudes === [] || $latitudes === []) {
            throw new RuntimeException('GDAL could not work out where this GeoTIFF is on Earth.');
        }

        return [min($longitudes), min($latitudes), max($longitudes), max($latitudes)];
    }

    private function mercatorY(float $latitude): float
    {
        $latitude = max(-85.0, min(85.0, $latitude));

        return self::EARTH_RADIUS * log(tan(M_PI / 4 + deg2rad($latitude) / 2));
    }

    /** RGB bands plus the alpha band when present, so no-data areas are skipped. */
    private function rgbBandArguments(array $info): array
    {
        $bands = $info['bands'] ?? [];
        if (count($bands) < 3) {
            throw new RuntimeException('Mangrove detection needs RGB imagery (at least 3 bands).');
        }

        $arguments = ['-b', '1', '-b', '2', '-b', '3'];
        foreach ($bands as $index => $band) {
            if ($index >= 3 && strcasecmp((string) ($band['colorInterpretation'] ?? ''), 'Alpha') === 0) {
                array_push($arguments, '-b', (string) ($index + 1));
                break;
            }
        }

        return $arguments;
    }

    /** @return array{0: float, 1: float, 2: float, 3: float} ulx, uly, lrx, lry */
    private function cornerCoordinates(array $info): array
    {
        [$originX, $pixelWidth, , $originY, , $pixelHeight] = $info['geoTransform'];
        [$width, $height] = $info['size'];

        return [
            (float) $originX,
            (float) $originY,
            (float) $originX + (float) $pixelWidth * $width,
            (float) $originY + (float) $pixelHeight * $height,
        ];
    }

    private function number(float $value): string
    {
        return rtrim(rtrim(sprintf('%.10F', $value), '0'), '.');
    }
}
