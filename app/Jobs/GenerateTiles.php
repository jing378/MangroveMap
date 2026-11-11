<?php

namespace App\Jobs;

use App\Models\TileSet;
use App\Models\TileSource;
use App\Services\GdalCommandRunner;
use App\Services\MangroveAreaDetector;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Tile Explorer: cut an uploaded GeoTIFF into equal square tiles (GeoTIFF + JPEG/world file + thumbnail) in its
 * own coordinate system with scripts/gdal/make_tiles.py. The first run per upload also detects the mangrove areas
 * with the AI (MangroveAreaDetector), which label every tile; if that fails the tiles are still made, unlabelled.
 * Runs on the drone imagery worker.
 */
class GenerateTiles implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 7200;

    public function __construct(public int $tileSetId)
    {
        $this->onConnection('drone_imagery')->onQueue('drone-imagery');
        $this->afterCommit();
    }

    public function handle(GdalCommandRunner $gdal, MangroveAreaDetector $detector): void
    {
        $tileSet = TileSet::with('source')->find($this->tileSetId);
        if ($tileSet === null || $tileSet->source === null) {
            return;
        }

        $source = $tileSet->source;
        $disk = Storage::disk('local');
        $directory = $tileSet->directory();
        $workDirectory = storage_path("app/private/tile-explorer-work/{$tileSet->id}");
        $timeout = (int) config('gdal.timeout');

        try {
            if (! $disk->exists($source->stored_path)) {
                throw new RuntimeException('The uploaded GeoTIFF is missing. Upload it again.');
            }
            File::ensureDirectoryExists($workDirectory);
            $rasterPath = $disk->path($source->stored_path);

            $info = json_decode($gdal->run(['gdalinfo', '-json', $rasterPath], $workDirectory, $timeout), true);
            $this->validateRaster($info);
            $source->update([
                'crs' => self::crsName($info),
                'width' => (int) $info['size'][0],
                'height' => (int) $info['size'][1],
            ]);

            if ($source->areas_status === null) {
                $tileSet->update(['status' => 'detecting', 'error_message' => null]);
                $this->detectAreas($detector, $source, $rasterPath, $info, $workDirectory);
            }

            $tileSet->update(['status' => 'processing', 'error_message' => null]);
            $disk->deleteDirectory($directory);
            if (! $disk->makeDirectory($directory)) {
                throw new RuntimeException('Unable to create the tile folder.');
            }

            $arguments = [
                'python', base_path('scripts/gdal/make_tiles.py'),
                '--raster', $rasterPath,
                '--out', $disk->path($directory),
                '--tile-size', (string) $tileSet->tile_size,
                '--prefix', self::filePrefix($source),
            ];
            if ($source->areas_status === 'ready' && $disk->exists($source->areasPath())) {
                array_push($arguments, '--areas', $disk->path($source->areasPath()));
            }

            $output = $gdal->run($arguments, $workDirectory, $timeout);
            $lines = preg_split('/\R/', trim($output));
            $summary = json_decode((string) end($lines), true);
            if (! is_array($summary) || isset($summary['error'])) {
                throw new RuntimeException('Tiling failed: '.($summary['error'] ?? 'the tiling script returned no result.'));
            }

            $manifest = $tileSet->manifest();
            if ($manifest === null) {
                throw new RuntimeException('Tiling finished without a tile list.');
            }
            $threshold = self::mangroveThreshold();
            $tileSet->update([
                'status' => 'ready',
                'tile_count' => count($manifest['tiles']),
                'mangrove_tile_count' => collect($manifest['tiles'])
                    ->filter(fn (array $tile) => ($tile['mangrove'] ?? null) !== null && $tile['mangrove'] >= $threshold)
                    ->count(),
                'columns' => $manifest['columns'] ?? null,
                'rows' => $manifest['rows'] ?? null,
                'error_message' => null,
                'generated_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $tileSet->update([
                'status' => 'failed',
                'error_message' => mb_substr($exception->getMessage(), 0, 2000),
            ]);
            Log::error('Tile generation failed.', ['tile_set_id' => $tileSet->id, 'exception' => $exception]);
        } finally {
            File::deleteDirectory($workDirectory);
        }
    }

    /** Minimum mangrove coverage (% of a tile's imagery) for the "Mangrove" label. */
    public static function mangroveThreshold(): float
    {
        return (float) config('gdal.tile_explorer.mangrove_min_percent', 5);
    }

    /** File names: <upload-name>_r<row>_c<col>, e.g. brgy-san-isidro-march-2026_r004_c012.tif */
    public static function filePrefix(TileSource $source): string
    {
        $slug = Str::slug(Str::limit((string) $source->name, 60, ''));

        return $slug !== '' ? $slug : "geotiff-{$source->id}";
    }

    /** EPSG code when GDAL knows it, else the coordinate system's name. */
    public static function crsName(array $info): ?string
    {
        if (! empty($info['stac']['proj:epsg'])) {
            return 'EPSG:'.$info['stac']['proj:epsg'];
        }
        $wkt = (string) ($info['coordinateSystem']['wkt'] ?? '');
        if (preg_match('/ID\["EPSG",(\d+)\]\]\s*$/', $wkt, $match)) {
            return 'EPSG:'.$match[1];
        }

        return preg_match('/^[A-Z]+CRS\["([^"]+)"/', $wkt, $match) ? Str::limit($match[1], 120, '') : null;
    }

    private function validateRaster(mixed $info): void
    {
        if (! is_array($info) || empty($info['size']) || empty($info['geoTransform'])) {
            throw new RuntimeException('GDAL could not read this file as a GeoTIFF.');
        }
        if (empty($info['coordinateSystem']['wkt'])) {
            throw new RuntimeException('This TIFF has no coordinate system, so it is not a GeoTIFF. Export it from your mapping software as a GeoTIFF and upload it again.');
        }
        $bands = $info['bands'] ?? [];
        if (count($bands) < 3) {
            throw new RuntimeException('Tile Explorer needs RGB imagery (at least 3 bands). This file has '.count($bands).'.');
        }
        if (($bands[0]['type'] ?? 'Byte') !== 'Byte') {
            throw new RuntimeException('Tile Explorer needs an 8-bit RGB GeoTIFF; this one is '.$bands[0]['type'].'. Export it as 8-bit (Byte) and upload it again.');
        }
    }

    /** Once per upload; a failure only means the tiles are not labelled. */
    private function detectAreas(MangroveAreaDetector $detector, TileSource $source, string $rasterPath, array $info, string $workDirectory): void
    {
        try {
            $result = $detector->detect($rasterPath, $info, $workDirectory);
            Storage::disk('local')->put($source->areasPath(), json_encode($result['geojson'], JSON_UNESCAPED_SLASHES));
            $source->update(['areas_status' => 'ready', 'areas_error' => null]);
        } catch (Throwable $exception) {
            $source->update(['areas_status' => 'failed', 'areas_error' => mb_substr($exception->getMessage(), 0, 2000)]);
            Log::warning('Tile Explorer mangrove detection failed; tiles are made without labels.', [
                'tile_source_id' => $source->id,
                'exception' => $exception,
            ]);
        }
    }
}
