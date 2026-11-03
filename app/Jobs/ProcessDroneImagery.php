<?php

namespace App\Jobs;

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

class ProcessDroneImagery implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
        $tileRelativePath = "drone-imagery/tiles/{$layer->id}";
        $tileDirectory = $localDisk->path($tileRelativePath);
        $workDirectory = storage_path("app/private/drone-imagery/{$layer->id}");
        $mosaicRelativePath = "drone-imagery/processed/{$layer->id}/mosaic_3857.tif";
        $mosaicPath = $localDisk->path($mosaicRelativePath);

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

            if (! $localDisk->makeDirectory(dirname($mosaicRelativePath))) {
                throw new RuntimeException('Unable to create the processed raster directory.');
            }

            if (! $localDisk->makeDirectory($tileRelativePath)) {
                throw new RuntimeException('Unable to create the tile output directory.');
            }

            $warpArguments = [
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
            ];
            $gdal->run($warpArguments, $workDirectory, (int) config('gdal.timeout'));

            $mosaicInfo = $this->readRasterInfo($gdal, $mosaicPath, $workDirectory);
            $bounds = $this->readWgs84Bounds($mosaicInfo);

            [$zoomMin, $zoomMax] = $this->tileZoomRange($mosaicInfo);
            $zoom = "{$zoomMin}-{$zoomMax}";
            $tileArguments = [
                'python',
                '-m',
                'osgeo_utils.gdal2tiles',
                '--profile=mercator',
                '--xyz',
                '--webviewer=none',
                '--exclude',
                '--resampling=bilinear',
                '--processes='.max(1, (int) config('gdal.tile_processes')),
            ];
            if (is_string($zoom) && preg_match('/^\d{1,2}-\d{1,2}$/', $zoom)) {
                $tileArguments[] = '--zoom='.$zoom;
            }
            $tileArguments[] = $mosaicPath;
            $tileArguments[] = $tileDirectory;

            $gdal->run($tileArguments, $workDirectory, (int) config('gdal.timeout'));

            $tileFiles = File::allFiles($tileDirectory);
            if (count($tileFiles) === 0) {
                throw new RuntimeException('GDAL2Tiles completed without generating any map tiles.');
            }

            $layer->update([
                'status' => 'ready',
                'source_crs' => $sourceCrs,
                'bounds' => $bounds,
                'metadata' => [
                    'source_count' => $layer->files->count(),
                    'source_size_bytes' => $totalSize,
                    'target_crs' => 'EPSG:3857',
                    'raster_size' => $mosaicInfo['size'] ?? null,
                    'tile_count' => count($tileFiles),
                    'tile_zoom' => $zoom,
                ],
                'mosaic_path' => $mosaicRelativePath,
                'tiles_path' => $tileRelativePath,
                'zoom_min' => $zoomMin,
                'zoom_max' => $zoomMax,
                'processed_at' => now(),
                'error_message' => null,
            ]);

            if (config('ml.drone.auto_segment')) {
                $this->queueSegmentation($layer);
            }
        } catch (Throwable $exception) {
            $localDisk->deleteDirectory($tileRelativePath);
            $localDisk->delete($mosaicRelativePath);
            $layer->update([
                'status' => 'failed',
                'error_message' => mb_substr($exception->getMessage(), 0, 2000),
            ]);
            Log::error('Drone imagery processing failed.', [
                'layer_id' => $layer->id,
                'exception' => $exception,
            ]);
        } finally {
            File::deleteDirectory($workDirectory);
        }
    }

    /** Segmentation failures must not mark the (already usable) imagery layer as failed. */
    private function queueSegmentation(DroneImageryLayer $layer): void
    {
        try {
            $layer->update(['segmentation_status' => 'queued', 'segmentation_error' => null]);
            SegmentDroneImagery::dispatch($layer->id);
        } catch (Throwable $exception) {
            $layer->update([
                'segmentation_status' => 'failed',
                'segmentation_error' => 'Unable to queue AI segmentation.',
            ]);
            Log::error('Unable to queue drone imagery segmentation.', [
                'layer_id' => $layer->id,
                'exception' => $exception,
            ]);
        }
    }

    private function readRasterInfo(GdalCommandRunner $gdal, string $path, string $workDirectory): array
    {
        $json = $gdal->run(['gdalinfo', '-json', $path], $workDirectory, (int) config('gdal.timeout'));
        $info = json_decode($json, true);

        if (! is_array($info)) {
            throw new RuntimeException('GDAL returned invalid metadata for a TIFF file.');
        }

        return $info;
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

    private function readWgs84Bounds(array $info): array
    {
        $coordinates = $info['wgs84Extent']['coordinates'][0] ?? null;
        if (! is_array($coordinates) || $coordinates === []) {
            throw new RuntimeException('GDAL could not determine geographic bounds for the processed raster.');
        }

        $longitudes = [];
        $latitudes = [];
        foreach ($coordinates as $coordinate) {
            if (! is_array($coordinate) || ! isset($coordinate[0], $coordinate[1])) {
                continue;
            }
            $longitudes[] = (float) $coordinate[0];
            $latitudes[] = (float) $coordinate[1];
        }

        if ($longitudes === [] || $latitudes === []) {
            throw new RuntimeException('GDAL returned invalid geographic bounds for the processed raster.');
        }

        $bounds = [
            'west' => min($longitudes),
            'south' => min($latitudes),
            'east' => max($longitudes),
            'north' => max($latitudes),
        ];
        if (
            $bounds['west'] < -180 || $bounds['east'] > 180
            || $bounds['south'] < -90 || $bounds['north'] > 90
            || $bounds['west'] >= $bounds['east']
            || $bounds['south'] >= $bounds['north']
        ) {
            throw new RuntimeException('The processed raster has invalid geographic bounds.');
        }

        return $bounds;
    }

    private function tileZoomRange(array $mosaicInfo): array
    {
        $configuredRange = config('gdal.tile_zoom');
        if (! is_string($configuredRange) || ! preg_match('/^(\d{1,2})-(\d{1,2}|auto)$/', $configuredRange, $matches)) {
            throw new RuntimeException('GDAL_TILE_ZOOM must be a zoom range such as 12-20 or 12-auto.');
        }

        $cap = min(22, max(0, (int) config('gdal.max_tile_zoom', 22)));
        $minimum = (int) $matches[1];
        $maximum = $matches[2] === 'auto'
            ? min($cap, $this->nativeZoom($mosaicInfo))
            : (int) $matches[2];
        $minimum = min($minimum, $maximum);
        if ($minimum < 0 || $maximum > 22 || $minimum > $maximum) {
            throw new RuntimeException('GDAL_TILE_ZOOM must be an ordered range between 0 and 22.');
        }

        return [$minimum, $maximum];
    }

    /**
     * Smallest web-mercator zoom whose 256 px tiles are at least as detailed as the mosaic,
     * so zooming in shows the full drone resolution instead of upscaled tiles.
     */
    private function nativeZoom(array $mosaicInfo): int
    {
        $pixelSize = abs((float) ($mosaicInfo['geoTransform'][1] ?? 0)); // EPSG:3857 units
        if ($pixelSize <= 0) {
            return 20;
        }

        // Tile pixel size at zoom z (EPSG:3857 units) = 40075016.686 / (256 * 2^z).
        $zoom = log(40075016.686 / (256 * $pixelSize), 2);

        // Round up only when the extra level adds meaningful detail (> ~30 % finer than z-1).
        return (int) max(0, ($zoom - floor($zoom)) > 0.4 ? ceil($zoom) : floor($zoom));
    }
}
