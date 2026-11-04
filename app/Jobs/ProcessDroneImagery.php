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
 * Step 1 of the drone pipeline: reproject the uploaded GeoTIFF(s) into one EPSG:3857 mosaic,
 * render a small preview image and queue AI segmentation of it. The layer then waits in
 * awaiting_review until the owner confirms it in the editor (where the AI extent can be used as
 * the areas to keep), which dispatches FinalizeDroneImagery (masking, tiles).
 */
class ProcessDroneImagery implements ShouldQueue
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
        $layer = DroneImageryLayer::with('files')->findOrFail($this->layerId);
        $localDisk = Storage::disk('local');
        $processedDirectory = "drone-imagery/processed/{$layer->id}";
        $tileRelativePath = "drone-imagery/tiles/{$layer->id}";
        $workDirectory = storage_path("app/private/drone-imagery/{$layer->id}");
        $mosaicRelativePath = "{$processedDirectory}/mosaic_3857.tif";
        $mosaicPath = $localDisk->path($mosaicRelativePath);
        $previewRelativePath = "{$processedDirectory}/preview.png";

        $layer->update([
            'status' => 'processing',
            'error_message' => null,
        ]);

        try {
            if ($layer->files->isEmpty()) {
                throw new RuntimeException('No uploaded TIFF files were found for this imagery layer.');
            }

            $inputPaths = [];
            $sourceCrs = null;
            $totalSize = 0;
            $alphaSources = [];
            foreach ($layer->files as $file) {
                if (! $localDisk->exists($file->stored_path)) {
                    throw new RuntimeException("Uploaded file is missing: {$file->original_name}");
                }

                $inputPath = $localDisk->path($file->stored_path);
                $info = $this->readRasterInfo($gdal, $inputPath, $workDirectory);
                $this->validateSourceRaster($info, $file->original_name);
                $alphaSources[$file->original_name] = $this->hasAlphaBand($info);
                $sourceCrs ??= $info['coordinateSystem']['wkt'];
                $inputPaths[] = $inputPath;
                $totalSize += $file->file_size;
            }

            // gdalwarp only auto-detects source alpha for a single input; with several RGBA
            // inputs it would copy alpha as a data band and add another (5 bands, which tiling rejects).
            $withAlpha = count(array_filter($alphaSources));
            if ($withAlpha > 0 && $withAlpha < count($alphaSources)) {
                throw new RuntimeException(
                    'This batch mixes RGB and RGBA (transparent) TIFFs. Export all files with the same settings and upload them again.'
                );
            }

            // Re-processing starts from scratch: drop tiles and outputs from an earlier run.
            $localDisk->deleteDirectory($tileRelativePath);
            $localDisk->deleteDirectory($processedDirectory);
            if (! $localDisk->makeDirectory($processedDirectory)) {
                throw new RuntimeException('Unable to create the processed raster directory.');
            }

            $gdal->run([
                'gdalwarp',
                '-overwrite',
                '-multi',
                '-wm',
                '512',
                '-t_srs',
                'EPSG:3857',
                '-r',
                'bilinear',
                ...($withAlpha > 0 ? ['-srcalpha'] : []),
                '-dstalpha',
                '-co',
                'TILED=YES',
                '-co',
                'COMPRESS=DEFLATE',
                '-co',
                'BIGTIFF=IF_SAFER',
                ...$inputPaths,
                $mosaicPath,
            ], $workDirectory, (int) config('gdal.timeout'));

            $mosaicInfo = $this->readRasterInfo($gdal, $mosaicPath, $workDirectory);
            $bounds = $this->readWgs84Bounds($mosaicInfo);
            $this->renderPreview($gdal, $mosaicInfo, $mosaicPath, $localDisk->path($previewRelativePath), $workDirectory);

            $layer->update([
                'status' => 'awaiting_review',
                'source_crs' => $sourceCrs,
                'bounds' => $bounds,
                'preview_bounds' => $bounds,
                'metadata' => [
                    'source_count' => $layer->files->count(),
                    'source_size_bytes' => $totalSize,
                    'target_crs' => 'EPSG:3857',
                    'raster_size' => $mosaicInfo['size'] ?? null,
                ],
                'mosaic_path' => $mosaicRelativePath,
                'preview_path' => $previewRelativePath,
                'final_mosaic_path' => null,
                'applied_edits' => null,
                'tiles_path' => null,
                'error_message' => null,
                'segmentation_status' => null,
                'segmentation_path' => null,
                'segmentation_stats' => null,
                'segmentation_error' => null,
                'segmented_at' => null,
            ]);

            // Detect mangroves before the review, so the editor can offer the AI extent as the areas to keep.
            if (config('ml.drone.auto_segment')) {
                $this->queueSegmentation($layer);
            }
        } catch (Throwable $exception) {
            $localDisk->deleteDirectory($tileRelativePath);
            $localDisk->deleteDirectory($processedDirectory);
            $layer->update([
                'status' => 'failed',
                'error_message' => mb_substr($exception->getMessage(), 0, 2000),
                'mosaic_path' => null,
                'preview_path' => null,
                'preview_bounds' => null,
                'final_mosaic_path' => null,
                'tiles_path' => null,
            ]);
            Log::error('Drone imagery processing failed.', [
                'layer_id' => $layer->id,
                'exception' => $exception,
            ]);
        } finally {
            File::deleteDirectory($workDirectory);
        }
    }

    /** Downscaled RGBA PNG of the mosaic for the review editor (L.imageOverlay over the mosaic bounds). */
    private function renderPreview(
        GdalCommandRunner $gdal,
        array $mosaicInfo,
        string $mosaicPath,
        string $previewPath,
        string $workDirectory,
    ): void {
        [$width, $height] = $mosaicInfo['size'];
        $maxDimension = max(256, (int) config('gdal.preview_max_dimension', 2048));
        $scale = min(1, $maxDimension / max($width, $height, 1));

        $gdal->run([
            'gdal_translate', '-q',
            '-of', 'PNG',
            '-outsize', (string) max(1, (int) round($width * $scale)), (string) max(1, (int) round($height * $scale)),
            '-r', 'average',
            '-co', 'ZLEVEL=6',
            $mosaicPath,
            $previewPath,
        ], $workDirectory, (int) config('gdal.timeout'));

        if (! is_file($previewPath)) {
            throw new RuntimeException('GDAL did not produce the imagery preview.');
        }
    }

    private function validateSourceRaster(array $info, string $fileName): void
    {
        if (! isset($info['size'], $info['bands']) || ! is_array($info['bands']) || $info['bands'] === []) {
            throw new RuntimeException("The uploaded file is not a readable raster: {$fileName}");
        }

        if (empty($info['coordinateSystem']['wkt']) || empty($info['geoTransform'])) {
            throw new RuntimeException("The TIFF has no usable CRS or georeferencing: {$fileName}");
        }

        $bands = $info['bands'];
        $bandCount = count($bands);
        $isRgb = $bandCount === 3 || ($bandCount === 4 && $this->hasAlphaBand($info));
        $isByte = collect($bands)->every(fn ($band) => ($band['type'] ?? null) === 'Byte');
        if (! $isRgb || ! $isByte) {
            throw new RuntimeException(
                "Only 8-bit RGB or RGBA orthomosaics are supported ({$fileName} has {$bandCount} band(s)"
                .($isByte ? '' : ', not 8-bit').'). Export a visible-colour (RGB) orthomosaic and upload it again.'
            );
        }
    }

    private function hasAlphaBand(array $info): bool
    {
        $last = end($info['bands']);

        return count($info['bands']) === 4
            && strcasecmp((string) ($last['colorInterpretation'] ?? ''), 'Alpha') === 0;
    }
}
