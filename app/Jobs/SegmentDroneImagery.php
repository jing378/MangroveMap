<?php

namespace App\Jobs;

use App\Models\DroneImageryLayer;
use App\Services\GdalCommandRunner;
use App\Services\MangroveSegmentationService;
use App\Services\SegmentationFeatureBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Runs the mangrove U-Net over a processed drone mosaic and stores the detected
 * extent as delineation-ready area features (drone-imagery/processed/{id}/ai_extent.json).
 */
class SegmentDroneImagery implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 7200;

    public function __construct(public int $layerId)
    {
        $this->onConnection('drone_imagery')->onQueue('drone-imagery');
        $this->afterCommit();
    }

    public function handle(
        GdalCommandRunner $gdal,
        MangroveSegmentationService $segmentation,
        SegmentationFeatureBuilder $featureBuilder,
    ): void {
        $layer = DroneImageryLayer::findOrFail($this->layerId);
        $localDisk = Storage::disk('local');
        $workDirectory = storage_path("app/private/drone-imagery/segmentation-work/{$layer->id}");
        $resultRelativePath = "drone-imagery/processed/{$layer->id}/ai_extent.json";
        $gdalTimeout = (int) config('gdal.timeout');

        $layer->update([
            'segmentation_status' => 'processing',
            'segmentation_error' => null,
        ]);

        try {
            if ($layer->status !== 'ready' || ! $layer->mosaic_path || ! $localDisk->exists($layer->mosaic_path)) {
                throw new RuntimeException('The processed mosaic for this layer is missing.');
            }

            File::ensureDirectoryExists($workDirectory);
            $mosaicPath = $localDisk->path($layer->mosaic_path);
            $inputPath = $workDirectory.DIRECTORY_SEPARATOR.'seg_input.tif';
            $maskPath = $workDirectory.DIRECTORY_SEPARATOR.'mask.png';
            $maskGeoPath = $workDirectory.DIRECTORY_SEPARATOR.'mask_geo.tif';
            $polygonsPath = $workDirectory.DIRECTORY_SEPARATOR.'polygons.gpkg';
            $geoJsonPath = $workDirectory.DIRECTORY_SEPARATOR.'extent.geojson';

            $mosaicInfo = $this->readRasterInfo($gdal, $mosaicPath, $workDirectory);
            $bands = $this->rgbBandArguments($mosaicInfo);
            // The mosaic is EPSG:3857, whose units are stretched by 1/cos(latitude) relative to ground metres.
            $mercatorScale = 1 / max(0.01, cos(deg2rad($this->centerLatitude($layer, $mosaicInfo))));
            $pixelSize = $this->resamplePixelSize($mosaicInfo, $mercatorScale);

            $gdal->run([
                'gdal_translate', '-q',
                ...$bands,
                '-tr', $this->number($pixelSize), $this->number($pixelSize),
                '-r', 'average',
                '-co', 'COMPRESS=NONE',
                '-co', 'INTERLEAVE=PIXEL',
                '-co', 'PROFILE=BASELINE',
                $mosaicPath,
                $inputPath,
            ], $workDirectory, $gdalTimeout);

            $inputInfo = $this->readRasterInfo($gdal, $inputPath, $workDirectory);
            $prediction = $segmentation->predictRaster($inputPath, $maskPath);

            // Re-attach the resampled raster's georeferencing to the mask, then vectorize it.
            $gdal->run([
                'gdal_translate', '-q',
                '-a_srs', 'EPSG:3857',
                '-a_ullr', ...array_map($this->number(...), $this->cornerCoordinates($inputInfo)),
                '-a_nodata', '0',
                $maskPath,
                $maskGeoPath,
            ], $workDirectory, $gdalTimeout);
            $gdal->run([
                'python', '-m', 'osgeo_utils.gdal_polygonize', '-q', '-overwrite',
                $maskGeoPath, '-b', '1', '-f', 'GPKG', $polygonsPath, 'extent', 'DN',
            ], $workDirectory, $gdalTimeout);
            $gdal->run([
                'ogr2ogr', '-overwrite',
                '-f', 'GeoJSON',
                '-t_srs', 'EPSG:4326',
                '-simplify', $this->number((float) config('ml.drone.simplify_metres', 1.0) * $mercatorScale),
                '-lco', 'COORDINATE_PRECISION=7',
                '-lco', 'RFC7946=YES',
                $geoJsonPath,
                $polygonsPath,
                'extent',
            ], $workDirectory, $gdalTimeout);

            $geoJson = json_decode((string) file_get_contents($geoJsonPath), true);
            if (! is_array($geoJson)) {
                throw new RuntimeException('Vectorizing the segmentation mask produced invalid GeoJSON.');
            }

            $built = $featureBuilder->build(
                $geoJson,
                (float) config('ml.drone.min_area_m2', 25),
                (int) config('ml.drone.max_features', 20),
                (int) config('ml.drone.max_vertices', 300),
            );

            $groundPixelM2 = ($pixelSize / $mercatorScale) ** 2;
            $stats = [
                'mangrove_area_ha' => round(((int) ($prediction['pixels_mangrove'] ?? 0)) * $groundPixelM2 / 10000, 3),
                'surveyed_area_ha' => round(((int) ($prediction['pixels_valid'] ?? 0)) * $groundPixelM2 / 10000, 3),
                'mangrove_coverage_percent' => (float) ($prediction['mangrove_coverage_percent'] ?? 0),
                'mean_confidence' => (float) ($prediction['mean_confidence'] ?? 0),
                'feature_count' => count($built['features']),
                'polygon_count' => $built['polygon_count'],
                'feature_area_ha' => round($built['kept_area_m2'] / 10000, 3),
                'resolution_m' => round($pixelSize / $mercatorScale, 3),
                'tiles' => (int) ($prediction['tiles'] ?? 0),
                'device' => $prediction['device'] ?? null,
            ];

            $localDisk->put($resultRelativePath, json_encode([
                'features' => $built['features'],
                'stats' => $stats,
            ], JSON_UNESCAPED_SLASHES));

            $layer->update([
                'segmentation_status' => 'ready',
                'segmentation_path' => $resultRelativePath,
                'segmentation_stats' => $stats,
                'segmentation_error' => null,
                'segmented_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $layer->update([
                'segmentation_status' => 'failed',
                'segmentation_error' => mb_substr($exception->getMessage(), 0, 2000),
            ]);
            Log::error('Drone imagery segmentation failed.', [
                'layer_id' => $layer->id,
                'exception' => $exception,
            ]);
        } finally {
            File::deleteDirectory($workDirectory);
        }
    }

    private function readRasterInfo(GdalCommandRunner $gdal, string $path, string $workDirectory): array
    {
        $info = json_decode($gdal->run(['gdalinfo', '-json', $path], $workDirectory, (int) config('gdal.timeout')), true);

        if (! is_array($info) || empty($info['geoTransform']) || empty($info['size'])) {
            throw new RuntimeException('GDAL returned invalid metadata for the mosaic.');
        }

        return $info;
    }

    /** RGB bands plus the alpha band when present, so no-data areas are skipped. */
    private function rgbBandArguments(array $info): array
    {
        $bands = $info['bands'] ?? [];
        if (count($bands) < 3) {
            throw new RuntimeException('Mangrove segmentation needs RGB imagery (at least 3 bands).');
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

    private function centerLatitude(DroneImageryLayer $layer, array $info): float
    {
        if (isset($layer->bounds['south'], $layer->bounds['north'])) {
            return ((float) $layer->bounds['south'] + (float) $layer->bounds['north']) / 2;
        }

        $latitudes = array_column($info['wgs84Extent']['coordinates'][0] ?? [], 1);

        return $latitudes !== [] ? array_sum($latitudes) / count($latitudes) : 0.0;
    }

    /** Target pixel size in mosaic (EPSG:3857) units: the configured ground resolution, capped by max dimension. */
    private function resamplePixelSize(array $info, float $mercatorScale): float
    {
        $nativePixel = abs((float) $info['geoTransform'][1]);
        [$width, $height] = $info['size'];
        $pixelSize = max($nativePixel, (float) config('ml.drone.resolution', 0.15) * $mercatorScale);

        $maxDimension = max(512, (int) config('ml.drone.max_dimension', 12000));
        $longestSide = max($width, $height) * $nativePixel;

        return max($pixelSize, $longestSide / $maxDimension);
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
