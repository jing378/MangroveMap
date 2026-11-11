<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateTiles;
use App\Models\TileSet;
use App\Models\TileSource;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;
use ZipStream\CompressionMethod;
use ZipStream\ZipStream;

/**
 * Tile Explorer (sidebar): upload a GeoTIFF (chunked, DroneImageryUploadController with purpose "tiles"),
 * cut it into equal tiles, browse them with their AI mangrove coverage and download a selection as one ZIP
 * (GeoTIFF and/or JPEG + world file, plus an index). Uploads here are private to the uploader and never
 * become drone surveys, map layers or delineations.
 */
class TileExplorerController extends Controller
{
    /** Cookie the page polls to know the browser has started receiving a ZIP (not encrypted, see bootstrap/app.php). */
    public const DOWNLOAD_COOKIE = 'tile_download_token';

    private const TILE_ID_PATTERN = '/^r\d{3,5}_c\d{3,5}$/';

    public function page(Request $request): View
    {
        $sources = TileSource::query()
            ->where('user_id', $request->user()->id)
            ->with('tileSets')
            ->latest()
            ->get();

        return view('users.tile-explorer', [
            'page' => [
                'sources' => $sources->map(fn (TileSource $source) => $this->sourcePayload($source))->values(),
                'tile_sizes' => TileSet::TILE_SIZES,
                'default_tile_size' => TileSet::DEFAULT_TILE_SIZE,
                'mangrove_threshold' => GenerateTiles::mangroveThreshold(),
                'uploads_url' => url('/drone-imagery/uploads'),
                'upload_parallel' => max(1, min(8, (int) config('gdal.upload_parallel', 4))),
                'max_file_bytes' => DroneImageryUploadController::MAX_FILE_BYTES,
                'sources_url' => url('/tile-explorer/sources'),
                'sets_url' => url('/tile-explorer/sets'),
                'download_cookie' => self::DOWNLOAD_COOKIE,
            ],
        ]);
    }

    /** Called when a chunked "tiles" upload completes: keep the file, make the first tile set and queue it. */
    public static function createFromUpload(User $user, ?string $name, array $file, string $partPath, int $tileSize): JsonResponse
    {
        $disk = Storage::disk('local');
        $storedPath = null;

        try {
            $tileSet = DB::transaction(function () use ($user, $name, $file, $partPath, $tileSize, $disk, &$storedPath) {
                $source = TileSource::create([
                    'user_id' => $user->id,
                    'name' => Str::limit($name ?: (string) preg_replace('/\.tiff?$/i', '', $file['name']), 120, '') ?: 'GeoTIFF',
                    'original_name' => $file['name'],
                    'stored_path' => '',
                    'file_size' => $file['size'],
                ]);
                $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) === 'tiff' ? 'tiff' : 'tif';
                $storedPath = "{$source->directory()}/source.{$extension}";
                if (! $disk->move($partPath, $storedPath)) {
                    throw new RuntimeException('Unable to save the uploaded GeoTIFF.');
                }
                $source->update(['stored_path' => $storedPath]);

                return $source->tileSets()->create(['tile_size' => $tileSize, 'status' => 'queued']);
            });
        } catch (Throwable $exception) {
            if ($storedPath !== null) {
                $disk->delete($storedPath);
            }
            throw $exception;
        }

        self::queue($tileSet);
        $tileSet->load('source.tileSets');

        return response()->json((new self)->sourcePayload($tileSet->source), 202);
    }

    /** Cut an uploaded GeoTIFF at another tile size (or again at the same size with force). */
    public function storeSet(Request $request, int $sourceId): JsonResponse
    {
        $validated = $request->validate([
            'tile_size' => ['required', 'integer', 'in:'.implode(',', TileSet::TILE_SIZES)],
            'force' => ['sometimes', 'boolean'],
        ]);
        $source = $this->source($request, $sourceId);

        $tileSet = TileSet::firstOrNew(['tile_source_id' => $source->id, 'tile_size' => (int) $validated['tile_size']]);
        $alreadyThere = $tileSet->exists && ($tileSet->isBusy() || ($tileSet->status === 'ready' && ! ($validated['force'] ?? false)));
        if (! $alreadyThere) {
            $tileSet->fill(['status' => 'queued', 'error_message' => null])->save();
            Storage::disk('local')->delete($tileSet->directory().'/progress.json');
            self::queue($tileSet);
        }

        return response()->json($this->setSummary($tileSet->refresh()), $alreadyThere ? 200 : 202);
    }

    public function destroySource(Request $request, int $sourceId): JsonResponse
    {
        $source = $this->source($request, $sourceId);
        if ($source->tileSets()->whereIn('status', ['detecting', 'processing'])->exists()) {
            return response()->json(['message' => 'Wait until the tiles are finished before deleting this GeoTIFF.'], 409);
        }
        $source->deleteWithFiles();

        return response()->json(['deleted' => true]);
    }

    /** Status while generating; the tile list once ready. */
    public function show(Request $request, int $tileSetId): JsonResponse
    {
        $tileSet = $this->tileSet($request, $tileSetId);
        $payload = $this->setSummary($tileSet);
        $payload['source'] = $this->sourcePayload($tileSet->source->load('tileSets'));

        if ($tileSet->status === 'ready' && ($manifest = $tileSet->manifest()) !== null) {
            $payload['crs'] = $manifest['crs'] ?? $tileSet->source->crs;
            $payload['pixel_size'] = $manifest['pixel_size'] ?? null;
            $payload['has_areas'] = (bool) ($manifest['has_areas'] ?? false);
            $payload['tiles'] = $manifest['tiles'];
        }

        return response()->json($payload);
    }

    /** A tile image: "thumb" (grid card) or "full" (the JPEG, for the preview). */
    public function image(Request $request, int $tileSetId, string $tileId, string $variant): BinaryFileResponse
    {
        $tileSet = $this->tileSet($request, $tileSetId);
        abort_unless(preg_match(self::TILE_ID_PATTERN, $tileId) === 1, 404);

        $path = $tileSet->directory().($variant === 'full' ? '/jpg/' : '/thumb/')."{$tileId}.jpg";
        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);

        return response()->file($disk->path($path), [
            'Content-Type' => 'image/jpeg',
            // Rebuilt in place when the tiles are made again, so keep the cache short (the page adds ?v=).
            'Cache-Control' => 'private, max-age=600',
        ]);
    }

    /** Streams the selected tiles as one ZIP, written while it is sent (no temp file, constant memory). */
    public function download(Request $request, int $tileSetId): StreamedResponse
    {
        $tileSet = $this->tileSet($request, $tileSetId);
        $ids = $request->input('ids');
        if (is_string($ids)) {
            $request->merge(['ids' => array_values(array_filter(explode(',', $ids)))]);
        }
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['string', 'regex:'.self::TILE_ID_PATTERN],
            'format' => ['required', 'in:tif,jpg,both'],
            'token' => ['nullable', 'string', 'regex:/^[A-Za-z0-9]{8,64}$/'],
        ]);

        $manifest = $tileSet->status === 'ready' ? $tileSet->manifest() : null;
        abort_if($manifest === null, 409, 'These tiles are not ready.');

        $wanted = array_flip($validated['ids']);
        $tiles = array_values(array_filter($manifest['tiles'], fn (array $tile) => isset($wanted[$tile['id']])));
        abort_if($tiles === [], 422, 'None of the selected tiles exist any more. Reload the page.');

        $source = $tileSet->source;
        $folder = GenerateTiles::filePrefix($source)."_{$tileSet->tile_size}px";
        $format = $validated['format'];
        $root = Storage::disk('local')->path($tileSet->directory());
        $threshold = GenerateTiles::mangroveThreshold();

        $response = new StreamedResponse(function () use ($tiles, $format, $root, $folder, $manifest, $source, $tileSet, $threshold) {
            set_time_limit(0);
            $zip = new ZipStream(
                sendHttpHeaders: false,
                defaultCompressionMethod: CompressionMethod::STORE, // JPEG / DEFLATE GeoTIFF: already compressed
                defaultEnableZeroHeader: true,
                enableZip64: true,
            );

            foreach ($tiles as $tile) {
                $id = $tile['id'];
                if ($format !== 'jpg' && is_file("{$root}/tif/{$id}.tif")) {
                    $zip->addFileFromPath("{$folder}/geotiff/{$tile['name']}.tif", "{$root}/tif/{$id}.tif");
                }
                if ($format !== 'tif' && is_file("{$root}/jpg/{$id}.jpg")) {
                    $zip->addFileFromPath("{$folder}/jpeg/{$tile['name']}.jpg", "{$root}/jpg/{$id}.jpg");
                    foreach (['.wld', '.jpg.aux.xml'] as $sidecar) {
                        if (is_file("{$root}/jpg/{$id}{$sidecar}")) {
                            $zip->addFileFromPath("{$folder}/jpeg/{$tile['name']}{$sidecar}", "{$root}/jpg/{$id}{$sidecar}");
                        }
                    }
                }
            }

            $zip->addFile("{$folder}/tiles.csv", $this->indexCsv($tiles, $threshold), compressionMethod: CompressionMethod::DEFLATE);
            $zip->addFile("{$folder}/tiles.geojson", $this->indexGeoJson($tiles, $threshold), compressionMethod: CompressionMethod::DEFLATE);
            $zip->addFile("{$folder}/README.txt", $this->readme($source, $tileSet, $manifest, count($tiles), $threshold), compressionMethod: CompressionMethod::DEFLATE);
            $zip->finish();
        });

        $response->headers->set('Content-Type', 'application/zip');
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition('attachment', "{$folder}_".count($tiles).'-tiles.zip'));
        $response->headers->set('X-Accel-Buffering', 'no');
        $response->headers->set('Cache-Control', 'no-store');
        if (! empty($validated['token'])) {
            $response->headers->setCookie(Cookie::create(self::DOWNLOAD_COOKIE, $validated['token'], 0, '/', null, $request->isSecure(), false, false, Cookie::SAMESITE_LAX));
        }

        return $response;
    }

    private static function queue(TileSet $tileSet): void
    {
        try {
            GenerateTiles::dispatch($tileSet->id);
        } catch (Throwable $exception) {
            $tileSet->update(['status' => 'failed', 'error_message' => 'Unable to queue tile generation. Please try again later.']);
            Log::error('Unable to queue tile generation.', ['tile_set_id' => $tileSet->id, 'exception' => $exception]);
        }
    }

    private function sourcePayload(TileSource $source): array
    {
        return [
            'id' => $source->id,
            'name' => $source->name,
            'original_name' => $source->original_name,
            'file_size' => $source->file_size,
            'crs' => $source->crs,
            'width' => $source->width,
            'height' => $source->height,
            'areas_status' => $source->areas_status,
            'areas_error' => $source->areas_status === 'failed' ? $this->friendlyAreasError($source->areas_error) : null,
            'uploaded_at' => $source->created_at?->toIso8601String(),
            'tile_sets' => $source->tileSets->sortBy('tile_size')->map(fn (TileSet $set) => $this->setSummary($set))->values(),
        ];
    }

    private function setSummary(TileSet $tileSet): array
    {
        $progress = $tileSet->status === 'processing' ? $tileSet->progress() : null;

        return [
            'id' => $tileSet->id,
            'source_id' => $tileSet->tile_source_id,
            'tile_size' => $tileSet->tile_size,
            'status' => $tileSet->status,
            'tile_count' => $tileSet->tile_count,
            'mangrove_tile_count' => $tileSet->mangrove_tile_count,
            'columns' => $tileSet->columns,
            'rows' => $tileSet->rows,
            'error_message' => $tileSet->error_message,
            'progress' => $progress && ($progress['total'] ?? 0) > 0
                ? round(min(100, $progress['done'] / $progress['total'] * 100), 1)
                : null,
            'generated_at' => $tileSet->generated_at?->toIso8601String(),
        ];
    }

    private function friendlyAreasError(?string $error): string
    {
        return str_contains((string) $error, 'weights are missing') || str_contains((string) $error, 'Python interpreter')
            ? 'The mangrove AI model is not installed on the server.'
            : 'The AI could not check this image for mangroves.';
    }

    private function indexCsv(array $tiles, float $threshold): string
    {
        $lines = ['file,row,column,mangrove,mangrove_percent,imagery_percent,west,south,east,north'];
        foreach ($tiles as $tile) {
            $mangrove = $tile['mangrove'] ?? null;
            [$west, $south, $east, $north] = $tile['bounds'];
            $lines[] = implode(',', [
                $tile['name'], $tile['row'], $tile['col'],
                $mangrove === null ? '' : ($mangrove >= $threshold ? 'yes' : 'no'),
                $mangrove ?? '', $tile['valid'], $west, $south, $east, $north,
            ]);
        }

        return implode("\n", $lines)."\n";
    }

    private function indexGeoJson(array $tiles, float $threshold): string
    {
        return json_encode([
            'type' => 'FeatureCollection',
            'features' => array_map(function (array $tile) use ($threshold) {
                [$west, $south, $east, $north] = $tile['bounds'];
                $mangrove = $tile['mangrove'] ?? null;

                return [
                    'type' => 'Feature',
                    'properties' => [
                        'file' => $tile['name'],
                        'row' => $tile['row'],
                        'column' => $tile['col'],
                        'mangrove' => $mangrove === null ? null : $mangrove >= $threshold,
                        'mangrove_percent' => $mangrove,
                        'imagery_percent' => $tile['valid'],
                    ],
                    'geometry' => [
                        'type' => 'Polygon',
                        'coordinates' => [[[$west, $south], [$east, $south], [$east, $north], [$west, $north], [$west, $south]]],
                    ],
                ];
            }, $tiles),
        ], JSON_UNESCAPED_SLASHES);
    }

    private function readme(TileSource $source, TileSet $tileSet, array $manifest, int $count, float $threshold): string
    {
        $pixel = isset($manifest['pixel_size']) ? round((float) $manifest['pixel_size'], 6).' map units' : 'unknown';

        return implode("\n", [
            "MangroveMap tiles: {$source->name} ({$source->original_name})",
            '',
            "{$count} tiles of {$tileSet->tile_size} x {$tileSet->tile_size} px, cut from the uploaded GeoTIFF.",
            'Coordinate system: '.($manifest['crs'] ?? 'unknown')." (same as the uploaded file), pixel size {$pixel}.",
            'File names end in _r<row>_c<column> (row 0 = top, column 0 = left of the image).',
            '',
            'geotiff/  GeoTIFF tiles (RGB + transparency) with their coordinates and coordinate system.',
            'jpeg/     JPEG tiles (RGB, e.g. for Roboflow). The .wld world file and .jpg.aux.xml keep their',
            '          position for GIS software. Areas outside the image are black.',
            'tiles.csv / tiles.geojson  every tile with its mangrove coverage and WGS84 footprint.',
            '',
            "\"mangrove = yes\" means the MangroveMap AI found mangroves in at least {$threshold}% of the tile's imagery.",
            'Empty when the AI could not check the image.',
            '',
            'Downloaded '.now()->toDateTimeString().'.',
            '',
        ]);
    }

    private function source(Request $request, int $sourceId): TileSource
    {
        return TileSource::query()->where('user_id', $request->user()->id)->findOrFail($sourceId);
    }

    private function tileSet(Request $request, int $tileSetId): TileSet
    {
        $tileSet = TileSet::with('source')->findOrFail($tileSetId);
        abort_unless($tileSet->source?->user_id === $request->user()->id, 404);

        return $tileSet;
    }
}
