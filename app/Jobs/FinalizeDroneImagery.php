<?php

namespace App\Jobs;

use App\Jobs\Concerns\InteractsWithDroneRasters;
use App\Models\DroneImageryLayer;
use App\Services\GdalCommandRunner;
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
 * Step 2 of the drone pipeline, run when the owner confirms the review editor: apply the saved
 * edits to the untouched mosaic (position offset, then remove/keep-only polygons) and tile the result.
 * The reviewed AI delineation is kept (moved with the imagery); AI segmentation is only queued when
 * there is none yet. Re-running it after new edits rebuilds from the original mosaic.
 */
class FinalizeDroneImagery implements ShouldQueue
{
    use Dispatchable, InteractsWithDroneRasters, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 7200;

    public function __construct(public int $layerId)
    {
        $this->onConnection('drone_imagery')->onQueue('drone-imagery');
        $this->afterCommit();
    }

    public function handle(GdalCommandRunner $gdal): void
    {
        $layer = DroneImageryLayer::findOrFail($this->layerId);
        $localDisk = Storage::disk('local');
        $processedDirectory = "drone-imagery/processed/{$layer->id}";
        $tileRelativePath = "drone-imagery/tiles/{$layer->id}";
        $tileDirectory = $localDisk->path($tileRelativePath);
        $workDirectory = storage_path("app/private/drone-imagery/finalize-work/{$layer->id}");
        $gdalTimeout = (int) config('gdal.timeout');
        $edits = $layer->currentEdits();

        $layer->update(['status' => 'finalizing', 'error_message' => null]);

        try {
            if ($layer->mosaic_path !== "{$processedDirectory}/mosaic_3857.tif" || ! $localDisk->exists($layer->mosaic_path)) {
                throw new RuntimeException('The prepared mosaic for this layer is missing. Upload the imagery again.');
            }

            File::ensureDirectoryExists($workDirectory);
            $this->deleteFinalOutputs($layer);
            $localDisk->deleteDirectory($tileRelativePath);
            if (! $localDisk->makeDirectory($tileRelativePath)) {
                throw new RuntimeException('Unable to create the tile output directory.');
            }

            $mosaicPath = $localDisk->path($layer->mosaic_path);
            $mosaicInfo = $this->readRasterInfo($gdal, $mosaicPath, $workDirectory);
            $sourcePath = $mosaicPath;
            $finalRelativePath = null;

            $east = (float) ($edits['offset']['east_m'] ?? 0);
            $north = (float) ($edits['offset']['north_m'] ?? 0);
            if ($east != 0.0 || $north != 0.0) {
                // A VRT next to the mosaic that only moves the corners: no pixel copy.
                $finalRelativePath = "{$processedDirectory}/final_shifted.vrt";
                $this->writeShiftedVrt($gdal, $layer, $mosaicInfo, $mosaicPath, $localDisk->path($finalRelativePath), $east, $north, $workDirectory);
                $sourcePath = $localDisk->path($finalRelativePath);
            }

            $polygons = $edits['polygons'] ?? [];
            if ($polygons !== []) {
                $maskPath = $this->writeMercatorMask($gdal, $polygons, $workDirectory);
                $maskedRelativePath = "{$processedDirectory}/final_masked.tif";
                $maskedPath = $localDisk->path($maskedRelativePath);

                // keep_ai = keep only the (owner-corrected) AI mangrove areas, sent as the polygons.
                if (in_array($edits['mode'] ?? 'remove', ['keep', 'keep_ai'], true)) {
                    // Keep only the drawn areas and crop the raster to them.
                    $gdal->run([
                        'gdalwarp', '-q', '-overwrite', '-multi', '-wm', '512',
                        '-cutline', $maskPath, '-cl', 'mask', '-crop_to_cutline',
                        '-srcalpha', '-dstalpha',
                        '-co', 'TILED=YES', '-co', 'COMPRESS=DEFLATE', '-co', 'BIGTIFF=IF_SAFER',
                        $sourcePath, $maskedPath,
                    ], $workDirectory, $gdalTimeout);
                } else {
                    // Copy the raster, then make the drawn areas transparent in its alpha band.
                    $gdal->run([
                        'gdal_translate', '-q',
                        '-co', 'TILED=YES', '-co', 'COMPRESS=DEFLATE', '-co', 'BIGTIFF=IF_SAFER',
                        $sourcePath, $maskedPath,
                    ], $workDirectory, $gdalTimeout);
                    $alphaBand = (string) count($mosaicInfo['bands'] ?? []);
                    $gdal->run([
                        'gdal_rasterize', '-q', '-b', $alphaBand, '-burn', '0', '-l', 'mask', $maskPath, $maskedPath,
                    ], $workDirectory, $gdalTimeout);
                }

                if ($finalRelativePath !== null) {
                    $localDisk->delete($finalRelativePath);
                }
                $finalRelativePath = $maskedRelativePath;
                $sourcePath = $maskedPath;
            }

            $finalInfo = $sourcePath === $mosaicPath ? $mosaicInfo : $this->readRasterInfo($gdal, $sourcePath, $workDirectory);
            $bounds = $this->readWgs84Bounds($finalInfo);
            $tiles = $this->generateTiles($gdal, $finalInfo, $sourcePath, $tileDirectory, $workDirectory);

            // The AI delineation is never thrown away here: it was reviewed (and maybe corrected) in the
            // editor, so it is kept and only moved along with the imagery. The owner can re-run the AI.
            $hasAiResult = $layer->segmentation_status === 'ready'
                && $layer->segmentation_path === "{$processedDirectory}/ai_extent.json"
                && $localDisk->exists($layer->segmentation_path);
            $aiStats = $hasAiResult ? $this->alignAiResult($layer, $edits['offset'] ?? []) : null;

            $layer->update([
                'status' => 'ready',
                'bounds' => $bounds,
                'metadata' => [
                    ...($layer->metadata ?? []),
                    'final_raster_size' => $finalInfo['size'] ?? null,
                    'tile_count' => $tiles['tile_count'],
                    'tile_zoom' => $tiles['zoom'],
                ],
                'final_mosaic_path' => $finalRelativePath,
                'applied_edits' => $edits,
                'tiles_path' => $tileRelativePath,
                'zoom_min' => $tiles['zoom_min'],
                'zoom_max' => $tiles['zoom_max'],
                'processed_at' => now(),
                'reviewed_at' => now(),
                'error_message' => null,
                ...($aiStats === null ? [] : ['segmentation_stats' => $aiStats, 'segmented_at' => now()]),
            ]);

            // Only when there is no AI delineation yet (never ran, or failed).
            if (! $hasAiResult && ! $layer->isSegmenting() && config('ml.drone.auto_segment')) {
                $this->queueSegmentation($layer);
            }
        } catch (Throwable $exception) {
            // Keep the mosaic, preview and AI delineation so the owner can adjust the edits and confirm again.
            $localDisk->deleteDirectory($tileRelativePath);
            $this->deleteFinalOutputs($layer);
            $layer->update([
                'status' => 'failed',
                'error_message' => mb_substr($exception->getMessage(), 0, 2000),
                'final_mosaic_path' => null,
                'tiles_path' => null,
            ]);
            Log::error('Drone imagery finalizing failed.', [
                'layer_id' => $layer->id,
                'exception' => $exception,
            ]);
        } finally {
            File::deleteDirectory($workDirectory);
        }
    }

    /**
     * Move the stored AI areas by the difference between the offset they were made at (stats.offset) and
     * the imagery's new offset, so they stay on top of the shifted imagery. Returns the updated stats.
     */
    private function alignAiResult(DroneImageryLayer $layer, array $offset): array
    {
        $disk = Storage::disk('local');
        $stats = $layer->segmentation_stats ?? [];
        $stored = $stats['offset'] ?? DroneImageryLayer::EMPTY_EDITS['offset'];
        $target = [
            'east_m' => (float) ($offset['east_m'] ?? 0),
            'north_m' => (float) ($offset['north_m'] ?? 0),
        ];
        $deltaEast = $target['east_m'] - (float) ($stored['east_m'] ?? 0);
        $deltaNorth = $target['north_m'] - (float) ($stored['north_m'] ?? 0);
        if (abs($deltaEast) < 0.001 && abs($deltaNorth) < 0.001) {
            return [...$stats, 'offset' => $target];
        }

        $result = json_decode((string) $disk->get($layer->segmentation_path), true);
        if (! is_array($result)) {
            return $stats;
        }

        $bounds = $layer->preview_bounds ?? $layer->bounds;
        $latitude = (((float) ($bounds['south'] ?? 0)) + ((float) ($bounds['north'] ?? 0))) / 2;
        $metresPerDegree = 111319.49;
        $deltaLat = $deltaNorth / $metresPerDegree;
        $deltaLng = $deltaEast / ($metresPerDegree * max(0.01, cos(deg2rad($latitude))));

        $result['features'] = array_map(fn (array $feature) => [
            ...$feature,
            'coords' => array_map(
                fn (array $point) => [round($point[0] + $deltaLat, 7), round($point[1] + $deltaLng, 7)],
                $feature['coords'] ?? []
            ),
        ], $result['features'] ?? []);
        $stats = [...$stats, 'offset' => $target];
        $result['stats'] = $stats;
        $disk->put($layer->segmentation_path, json_encode($result, JSON_UNESCAPED_SLASHES));

        return $stats;
    }

    private function deleteFinalOutputs(DroneImageryLayer $layer): void
    {
        $disk = Storage::disk('local');
        foreach (['final_shifted.vrt', 'final_masked.tif', 'final_masked.tif.aux.xml'] as $name) {
            $disk->delete("drone-imagery/processed/{$layer->id}/{$name}");
        }
    }

    /** Move the mosaic by a ground offset in metres (EPSG:3857 units are metres / cos(latitude)). */
    private function writeShiftedVrt(
        GdalCommandRunner $gdal,
        DroneImageryLayer $layer,
        array $mosaicInfo,
        string $mosaicPath,
        string $vrtPath,
        float $eastMetres,
        float $northMetres,
        string $workDirectory,
    ): void {
        $bounds = $layer->preview_bounds ?? $layer->bounds;
        $latitude = (((float) ($bounds['south'] ?? 0)) + ((float) ($bounds['north'] ?? 0))) / 2;
        $scale = 1 / max(0.01, cos(deg2rad($latitude)));

        [$originX, $pixelWidth, , $originY, , $pixelHeight] = $mosaicInfo['geoTransform'];
        [$width, $height] = $mosaicInfo['size'];
        $dx = $eastMetres * $scale;
        $dy = $northMetres * $scale;

        $gdal->run([
            'gdal_translate', '-q', '-of', 'VRT',
            '-a_ullr',
            $this->number((float) $originX + $dx),
            $this->number((float) $originY + $dy),
            $this->number((float) $originX + (float) $pixelWidth * $width + $dx),
            $this->number((float) $originY + (float) $pixelHeight * $height + $dy),
            $mosaicPath,
            $vrtPath,
        ], $workDirectory, (int) config('gdal.timeout'));
    }

    /**
     * Write the edit polygons (WGS84 [lng, lat] rings) as a GeoJSON layer named "mask", reprojected
     * to EPSG:3857 so gdal_rasterize and gdalwarp -cutline read it in the raster's own coordinates.
     */
    private function writeMercatorMask(GdalCommandRunner $gdal, array $polygons, string $workDirectory): string
    {
        $features = array_map(function (array $ring) {
            $first = $ring[0];
            $last = end($ring);
            if ($first !== $last) {
                $ring[] = $first;
            }

            return [
                'type' => 'Feature',
                'properties' => (object) [],
                'geometry' => ['type' => 'Polygon', 'coordinates' => [$ring]],
            ];
        }, $polygons);

        $wgs84Path = $workDirectory.DIRECTORY_SEPARATOR.'mask_4326.geojson';
        $mercatorPath = $workDirectory.DIRECTORY_SEPARATOR.'mask_3857.geojson';
        file_put_contents($wgs84Path, json_encode([
            'type' => 'FeatureCollection',
            'features' => $features,
        ], JSON_UNESCAPED_SLASHES));

        $gdal->run([
            'ogr2ogr', '-overwrite',
            '-f', 'GeoJSON',
            '-s_srs', 'EPSG:4326',
            '-t_srs', 'EPSG:3857',
            '-nln', 'mask',
            '-makevalid',
            $mercatorPath,
            $wgs84Path,
        ], $workDirectory, (int) config('gdal.timeout'));

        return $mercatorPath;
    }
}
