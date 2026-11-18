<?php

namespace App\Jobs;

use App\Jobs\Concerns\PreparesAiRaster;
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
 * AI delineation: detects where the mangroves are on a processed drone mosaic and stores them as
 * delineation-ready area features (drone-imagery/processed/{id}/ai_extent.json). See
 * MangroveSegmentationService::predictExtent for which model draws them (the U-Net by default).
 *
 * The genus is not part of it: when the areas are ready, ClassifyDroneImagery is queued to classify them
 * (genus suggestion and canopies). That job has its own status and can be re-run on its own.
 */
class SegmentDroneImagery implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, PreparesAiRaster, Queueable, SerializesModels;

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

        // New areas: the genus classified for the old ones no longer applies.
        $localDisk->delete([$layer->genusLabelsPath(), $layer->genusLabelsMetaPath(), $layer->canopiesPath()]);
        $layer->update([
            'segmentation_status' => 'processing',
            'segmentation_error' => null,
            'classification_status' => null,
            'classification_stats' => null,
            'classification_error' => null,
            'classified_at' => null,
        ]);

        try {
            File::ensureDirectoryExists($workDirectory);
            $raster = $this->prepareAiRaster($gdal, $layer, $workDirectory);
            $maskPath = $workDirectory.DIRECTORY_SEPARATOR.'mask.png';
            $maskGeoPath = $workDirectory.DIRECTORY_SEPARATOR.'mask_geo.tif';
            $polygonsPath = $workDirectory.DIRECTORY_SEPARATOR.'polygons.gpkg';
            $geoJsonPath = $workDirectory.DIRECTORY_SEPARATOR.'extent.geojson';

            $prediction = $segmentation->predictExtent($raster['input_path'], $maskPath);

            // Re-attach the resampled raster's georeferencing to the mask, then vectorize it.
            $gdal->run([
                'gdal_translate', '-q',
                '-a_srs', 'EPSG:3857',
                '-a_ullr', ...array_map($this->number(...), $raster['bounds']),
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
                '-simplify', $this->number((float) config('ml.drone.simplify_metres', 1.0) * $raster['mercator_scale']),
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

            $groundPixelM2 = $raster['pixel_m'] ** 2;
            $stats = [
                'mangrove_area_ha' => round(((int) ($prediction['pixels_mangrove'] ?? 0)) * $groundPixelM2 / 10000, 3),
                'surveyed_area_ha' => round(((int) ($prediction['pixels_valid'] ?? 0)) * $groundPixelM2 / 10000, 3),
                'mangrove_coverage_percent' => (float) ($prediction['mangrove_coverage_percent'] ?? 0),
                'mean_confidence' => (float) ($prediction['mean_confidence'] ?? 0),
                'feature_count' => count($built['features']),
                'polygon_count' => $built['polygon_count'],
                'feature_area_ha' => round($built['kept_area_m2'] / 10000, 3),
                'resolution_m' => round($raster['pixel_m'], 3),
                'tiles' => (int) ($prediction['tiles'] ?? 0),
                'device' => $prediction['device'] ?? null,
                // Which model drew the extent: "yolo" (the canopy model) or "unet".
                'extent_model' => (string) ($prediction['extent_model'] ?? 'unet'),
                // Position nudge already contained in the coordinates (the edited raster is shifted),
                // so the review editor can line the areas up with the preview.
                'offset' => $raster['offset'],
                'edited' => false,
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

            return;
        } finally {
            File::deleteDirectory($workDirectory);
        }

        // The areas are ready (the review editor can show them now): classify the genus next.
        ClassifyDroneImagery::queueFor($layer);
    }
}
