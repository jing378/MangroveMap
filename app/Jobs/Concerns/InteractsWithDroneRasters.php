<?php

namespace App\Jobs\Concerns;

use App\Jobs\SegmentDroneImagery;
use App\Models\DroneImageryLayer;
use App\Services\GdalCommandRunner;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/** GDAL helpers shared by ProcessDroneImagery (prepare + preview) and FinalizeDroneImagery (edits + tiles). */
trait InteractsWithDroneRasters
{
    private function readRasterInfo(GdalCommandRunner $gdal, string $path, string $workDirectory): array
    {
        $json = $gdal->run(['gdalinfo', '-json', $path], $workDirectory, (int) config('gdal.timeout'));
        $info = json_decode($json, true);

        if (! is_array($info)) {
            throw new RuntimeException('GDAL returned invalid metadata for a TIFF file.');
        }

        return $info;
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

    /**
     * Build XYZ tiles for $rasterPath into $tileDirectory.
     *
     * @return array{zoom_min: int, zoom_max: int, zoom: string, tile_count: int}
     */
    private function generateTiles(
        GdalCommandRunner $gdal,
        array $rasterInfo,
        string $rasterPath,
        string $tileDirectory,
        string $workDirectory,
    ): array {
        [$zoomMin, $zoomMax] = $this->tileZoomRange($rasterInfo);
        $zoom = "{$zoomMin}-{$zoomMax}";

        $gdal->run([
            'python',
            '-m',
            'osgeo_utils.gdal2tiles',
            '--profile=mercator',
            '--xyz',
            '--webviewer=none',
            '--exclude',
            '--resampling=bilinear',
            '--processes='.max(1, (int) config('gdal.tile_processes')),
            '--zoom='.$zoom,
            $rasterPath,
            $tileDirectory,
        ], $workDirectory, (int) config('gdal.timeout'));

        $tileCount = is_dir($tileDirectory) ? count(File::allFiles($tileDirectory)) : 0;
        if ($tileCount === 0) {
            throw new RuntimeException(
                'GDAL2Tiles completed without generating any map tiles. If you removed areas, check that some imagery is left.'
            );
        }

        return ['zoom_min' => $zoomMin, 'zoom_max' => $zoomMax, 'zoom' => $zoom, 'tile_count' => $tileCount];
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

    private function number(float $value): string
    {
        return rtrim(rtrim(sprintf('%.10F', $value), '0'), '.');
    }
}
