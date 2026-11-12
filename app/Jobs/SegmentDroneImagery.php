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
 * Detects the mangroves on a processed drone mosaic and stores the extent as delineation-ready
 * area features (drone-imagery/processed/{id}/ai_extent.json), the genus suggestion and every
 * canopy the canopy model pinpointed (canopies.json). See MangroveSegmentationService::predictExtent
 * for which model draws the extent.
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
            // The reviewed raster (removed areas transparent, cropped or shifted) when the owner edited it.
            $rasterRelativePath = $layer->final_mosaic_path ?? $layer->mosaic_path;
            // awaiting_review: runs on the unedited mosaic so the review editor can offer the AI extent.
            if (! in_array($layer->status, ['ready', 'awaiting_review'], true)
                || ! $rasterRelativePath || ! $localDisk->exists($rasterRelativePath)) {
                throw new RuntimeException('The processed mosaic for this layer is missing.');
            }

            File::ensureDirectoryExists($workDirectory);
            $mosaicPath = $localDisk->path($rasterRelativePath);
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
            // Position nudge already contained in the raster (the edited raster is shifted).
            $offset = $layer->final_mosaic_path
                ? ($layer->applied_edits['offset'] ?? DroneImageryLayer::EMPTY_EDITS['offset'])
                : DroneImageryLayer::EMPTY_EDITS['offset'];
            // The U-Net delineates the extent; the canopy model (YOLO, best.pt) then suggests the genus and
            // pinpoints every canopy inside it. With ml.drone.extent_model = yolo the canopy model does all three.
            ['prediction' => $prediction, 'genus_suggestion' => $genusSuggestion, 'canopies' => $canopies]
                = $this->detect($segmentation, $layer, $inputPath, $maskPath, [
                    'bounds' => $this->cornerCoordinates($inputInfo),
                    'latitude' => $this->centerLatitude($layer, $mosaicInfo),
                    'offset' => $offset,
                    'pixel_m' => $pixelSize / $mercatorScale, // ground size of one pixel of the resampled raster
                ]);

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
                // Which model drew the extent: "yolo" (the canopy model) or "unet".
                'extent_model' => (string) ($prediction['extent_model'] ?? 'unet'),
                // Position nudge already contained in the coordinates (the edited raster is shifted),
                // so the review editor can line the areas up with the preview.
                'offset' => $offset,
                'edited' => false,
                // Kept through the owner's area corrections (updateSegmentation merges the old stats).
                'genus_suggestion' => $genusSuggestion,
                // Canopies pinpointed by the canopy model (canopies.json); null when it did not run.
                'canopy_count' => $canopies['count'] ?? null,
                'canopies_by_genus' => $canopies['by_genus'] ?? null,
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

    /**
     * Mangrove extent mask of the resampled raster ($maskPath), plus what the canopy model (YOLO) gives:
     * the genus suggestion offered in the review editor and every canopy it pinpointed.
     *
     * By default the U-Net draws the extent and the canopy model runs inside that mask (genus shares and
     * canopies counted there only). With ml.drone.extent_model = yolo one canopy-model run gives all three.
     * The canopy model is optional: no weights means no suggestion and no canopies, and its failure never
     * fails the extent detection.
     *
     * Also keeps the per-pixel genus map (genus_labels.png + .json) so the suggestion can be recounted
     * for the owner's corrected areas without running the model again (DroneImageryController::updateSegmentation).
     *
     * $position is where the raster sits: EPSG:3857 corners [ulx, uly, lrx, lry], centre latitude, the
     * position offset already contained in it and the ground size of one pixel.
     *
     * @param  array{bounds: array<int, float>, latitude: float, offset: array{east_m: float, north_m: float}, pixel_m: float}  $position
     * @return array{prediction: array<string, mixed>, genus_suggestion: array<string, mixed>|null, canopies: array<string, mixed>|null}
     */
    private function detect(
        MangroveSegmentationService $segmentation,
        DroneImageryLayer $layer,
        string $inputPath,
        string $maskPath,
        array $position,
    ): array {
        // Results of an earlier run no longer match this one.
        Storage::disk('local')->delete([$layer->genusLabelsPath(), $layer->genusLabelsMetaPath(), $layer->canopiesPath()]);

        $weights = $segmentation->genusWeightsPath();
        $options = $weights === null ? [] : $this->canopyModelOptions($maskPath, $position);

        $prediction = $segmentation->predictExtent($inputPath, $maskPath, $options);
        if (($prediction['extent_model'] ?? null) === 'yolo') {
            return [
                'prediction' => $prediction,
                'genus_suggestion' => $this->storeGenusRun($layer, $prediction, $options, $position),
                'canopies' => $this->storeCanopies($layer, $options),
            ];
        }

        if ($weights === null) {
            return ['prediction' => $prediction, 'genus_suggestion' => null, 'canopies' => null];
        }

        try {
            $result = $segmentation->predictGenera(
                $inputPath, $maskPath, $weights, $options['labels_out'], $options['min_patch_px'], $options
            );
        } catch (Throwable $exception) {
            Log::warning('Drone imagery genus suggestion failed.', [
                'layer_id' => $layer->id,
                'exception' => $exception,
            ]);

            return [
                'prediction' => $prediction,
                'genus_suggestion' => ['error' => mb_substr($exception->getMessage(), 0, 500)],
                'canopies' => null,
            ];
        }

        return [
            'prediction' => $prediction,
            'genus_suggestion' => $this->storeGenusRun($layer, $result, $options, $position),
            'canopies' => $this->storeCanopies($layer, $options),
        ];
    }

    /**
     * Where the canopy model writes its outputs (next to the mask) and its size thresholds for this raster.
     *
     * @return array<string, mixed>
     */
    private function canopyModelOptions(string $maskPath, array $position): array
    {
        $workDirectory = dirname($maskPath);
        $pixelM = max(0.001, (float) ($position['pixel_m'] ?? 0.15));
        $pixelsFor = fn (float $groundM2) => (int) ceil(max(0.0, $groundM2) / ($pixelM ** 2));

        return [
            'labels_out' => $workDirectory.DIRECTORY_SEPARATOR.'genus_labels.png',
            'canopies_out' => $workDirectory.DIRECTORY_SEPARATOR.'canopies.json',
            'bounds' => array_map('floatval', $position['bounds']),
            'pixel_m' => $pixelM,
            // Smallest patch of one genus that counts, in pixels of this raster (edge slivers are ignored).
            'min_patch_px' => $pixelsFor((float) config('ml.genus.min_patch_m2', 2.0)),
            // Smallest canopy worth pinpointing.
            'canopy_min_px' => $pixelsFor((float) config('ml.genus.canopy_min_area_m2', 1.0)),
            'close_iterations' => (int) config('ml.drone.close_iterations', 2),
        ];
    }

    /**
     * The genus suggestion from a canopy-model result, keeping its genus map for later recounts.
     *
     * @return array<string, mixed>
     */
    private function storeGenusRun(DroneImageryLayer $layer, array $result, array $options, array $position): array
    {
        $disk = Storage::disk('local');
        $suggestion = MangroveSegmentationService::genusSuggestionFromResult($result);

        $labelsWorkPath = (string) ($options['labels_out'] ?? '');
        if ($labelsWorkPath !== '' && is_file($labelsWorkPath)) {
            $disk->put($layer->genusLabelsPath(), (string) file_get_contents($labelsWorkPath));
            $disk->put($layer->genusLabelsMetaPath(), json_encode([
                'classes' => array_values((array) ($result['classes'] ?? [])),
                'bounds' => array_map('floatval', $position['bounds']),
                'latitude' => (float) $position['latitude'],
                'offset' => [
                    'east_m' => (float) ($position['offset']['east_m'] ?? 0),
                    'north_m' => (float) ($position['offset']['north_m'] ?? 0),
                ],
                'conf' => (float) config('ml.genus.confidence', 0.1),
                'prob_conf' => (float) config('ml.genus.probability_confidence', 0.01),
                'min_patch_px' => (int) ($options['min_patch_px'] ?? 0),
            ], JSON_UNESCAPED_SLASHES));
            $suggestion['recountable'] = true;
        }

        return $suggestion;
    }

    /**
     * Keeps the pinpointed canopies (canopies.json) with the layer and returns their summary.
     *
     * @return array{count: int, kept: int, by_genus: array<string, int>}|null
     */
    private function storeCanopies(DroneImageryLayer $layer, array $options): ?array
    {
        $workPath = (string) ($options['canopies_out'] ?? '');
        if ($workPath === '' || ! is_file($workPath)) {
            return null;
        }

        $contents = (string) file_get_contents($workPath);
        $decoded = json_decode($contents, true);
        if (! is_array($decoded) || ! is_array($decoded['canopies'] ?? null)) {
            return null;
        }

        Storage::disk('local')->put($layer->canopiesPath(), $contents);

        return [
            'count' => (int) ($decoded['count'] ?? count($decoded['canopies'])),
            'kept' => (int) ($decoded['kept'] ?? count($decoded['canopies'])),
            'by_genus' => array_map('intval', (array) ($decoded['by_genus'] ?? [])),
        ];
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
