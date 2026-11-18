<?php

namespace App\Jobs\Concerns;

use App\Models\DroneImageryLayer;
use App\Services\GdalCommandRunner;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * The resampled RGB(A) raster both AI jobs run on (SegmentDroneImagery for the mangrove areas,
 * ClassifyDroneImagery for the genus). Same settings, so both land on the same pixel grid.
 */
trait PreparesAiRaster
{
    /**
     * Resamples the layer's reviewed (or unedited) mosaic to the AI ground resolution in $workDirectory.
     *
     * @return array{input_path: string, bounds: array{0: float, 1: float, 2: float, 3: float}, latitude: float, offset: array{east_m: float, north_m: float}, pixel_size: float, mercator_scale: float, pixel_m: float}
     */
    private function prepareAiRaster(GdalCommandRunner $gdal, DroneImageryLayer $layer, string $workDirectory): array
    {
        $localDisk = Storage::disk('local');
        // The reviewed raster (removed areas transparent, cropped or shifted) when the owner edited it.
        $rasterRelativePath = $layer->final_mosaic_path ?? $layer->mosaic_path;
        // awaiting_review: runs on the unedited mosaic so the review editor can offer the AI result.
        if (! in_array($layer->status, ['ready', 'awaiting_review'], true)
            || ! $rasterRelativePath || ! $localDisk->exists($rasterRelativePath)) {
            throw new RuntimeException('The processed mosaic for this layer is missing.');
        }

        $mosaicPath = $localDisk->path($rasterRelativePath);
        $inputPath = $workDirectory.DIRECTORY_SEPARATOR.'seg_input.tif';
        $gdalTimeout = (int) config('gdal.timeout');

        $mosaicInfo = $this->readAiRasterInfo($gdal, $mosaicPath, $workDirectory);
        $latitude = $this->centerLatitude($layer, $mosaicInfo);
        // The mosaic is EPSG:3857, whose units are stretched by 1/cos(latitude) relative to ground metres.
        $mercatorScale = 1 / max(0.01, cos(deg2rad($latitude)));
        $pixelSize = $this->resamplePixelSize($mosaicInfo, $mercatorScale);

        $gdal->run([
            'gdal_translate', '-q',
            ...$this->rgbBandArguments($mosaicInfo),
            '-tr', $this->number($pixelSize), $this->number($pixelSize),
            '-r', 'average',
            '-co', 'COMPRESS=NONE',
            '-co', 'INTERLEAVE=PIXEL',
            '-co', 'PROFILE=BASELINE',
            $mosaicPath,
            $inputPath,
        ], $workDirectory, $gdalTimeout);

        $inputInfo = $this->readAiRasterInfo($gdal, $inputPath, $workDirectory);
        // Position nudge already contained in the raster (the edited raster is shifted).
        $offset = $layer->final_mosaic_path
            ? ($layer->applied_edits['offset'] ?? DroneImageryLayer::EMPTY_EDITS['offset'])
            : DroneImageryLayer::EMPTY_EDITS['offset'];

        return [
            'input_path' => $inputPath,
            'bounds' => $this->cornerCoordinates($inputInfo),
            'latitude' => $latitude,
            'offset' => [
                'east_m' => (float) ($offset['east_m'] ?? 0),
                'north_m' => (float) ($offset['north_m'] ?? 0),
            ],
            'pixel_size' => $pixelSize,
            'mercator_scale' => $mercatorScale,
            'pixel_m' => $pixelSize / $mercatorScale, // ground size of one pixel of the resampled raster
        ];
    }

    private function readAiRasterInfo(GdalCommandRunner $gdal, string $path, string $workDirectory): array
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
