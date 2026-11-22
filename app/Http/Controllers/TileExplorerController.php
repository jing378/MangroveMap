<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateTiles;
use App\Models\DroneImageryLayer;
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
 * cut it into equal tiles, browse them with their AI mangrove coverage and download a selection as GeoTIFFs
 * (one tile as its .tif, several as a ZIP of .tif files). Uploads here are private to the uploader and become a
 * drone layer only when the owner sends selected tiles to Drone Imagery (sendToDroneImagery, a copy of their GeoTIFFs).
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
    /**
     * The selected tiles as GeoTIFFs: one tile is sent as its .tif, two or more as a ZIP holding only the .tif files.
     */
    public function download(Request $request, int $tileSetId): BinaryFileResponse|StreamedResponse
    {
        $tileSet = $this->tileSet($request, $tileSetId);
        $ids = $request->input('ids');
        if (is_string($ids)) {
            $request->merge(['ids' => array_values(array_filter(explode(',', $ids)))]);
        }
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['string', 'regex:'.self::TILE_ID_PATTERN],
            'token' => ['nullable', 'string', 'regex:/^[A-Za-z0-9]{8,64}$/'],
        ]);

        $manifest = $tileSet->status === 'ready' ? $tileSet->manifest() : null;
        abort_if($manifest === null, 409, 'These tiles are not ready.');

        $root = Storage::disk('local')->path($tileSet->directory());
        $wanted = array_flip($validated['ids']);
        $tiles = array_values(array_filter(
            $manifest['tiles'],
            fn (array $tile) => isset($wanted[$tile['id']]) && is_file("{$root}/tif/{$tile['id']}.tif"),
        ));
        abort_if($tiles === [], 422, 'None of the selected tiles exist any more. Reload the page.');

        if (count($tiles) === 1) {
            $tile = $tiles[0];
            $response = new BinaryFileResponse("{$root}/tif/{$tile['id']}.tif", 200, [
                'Content-Type' => 'image/tiff',
                'Cache-Control' => 'no-store',
            ]);
            $response->setContentDisposition('attachment', "{$tile['name']}.tif");
        } else {
            $response = new StreamedResponse(function () use ($tiles, $root) {
                set_time_limit(0);
                $zip = new ZipStream(
                    sendHttpHeaders: false,
                    defaultCompressionMethod: CompressionMethod::STORE, // DEFLATE GeoTIFFs: already compressed
                    defaultEnableZeroHeader: true,
                    enableZip64: true,
                );
                foreach ($tiles as $tile) {
                    $zip->addFileFromPath("{$tile['name']}.tif", "{$root}/tif/{$tile['id']}.tif");
                }
                $zip->finish();
            });

            $folder = GenerateTiles::filePrefix($tileSet->source)."_{$tileSet->tile_size}px";
            $response->headers->set('Content-Type', 'application/zip');
            $response->headers->set('Content-Disposition', $response->headers->makeDisposition('attachment', "{$folder}_".count($tiles).'-tiles.zip'));
            $response->headers->set('X-Accel-Buffering', 'no');
            $response->headers->set('Cache-Control', 'no-store');
        }

        if (! empty($validated['token'])) {
            $response->headers->setCookie(Cookie::create(self::DOWNLOAD_COOKIE, $validated['token'], 0, '/', null, $request->isSecure(), false, false, Cookie::SAMESITE_LAX));
        }

        return $response;
    }

    /**
     * "Send to Drone Imagery": the selected tiles' GeoTIFFs become one new drone layer (copied, so the tiles stay
     * here), processed like a Drone Upload batch: mosaic, map tiles, then the AI delineation.
     */
    public function sendToDroneImagery(Request $request, int $tileSetId): JsonResponse
    {
        $tileSet = $this->tileSet($request, $tileSetId);
        $maxFiles = max(1, (int) config('gdal.max_upload_files', 100));
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', "max:{$maxFiles}"],
            'ids.*' => ['string', 'distinct', 'regex:'.self::TILE_ID_PATTERN],
            'name' => ['nullable', 'string', 'max:120'],
        ], [
            'ids.max' => "Select at most {$maxFiles} tiles to send to Drone Imagery at once.",
        ]);

        $manifest = $tileSet->status === 'ready' ? $tileSet->manifest() : null;
        abort_if($manifest === null, 409, 'These tiles are not ready.');

        $disk = Storage::disk('local');
        $wanted = array_flip($validated['ids']);
        $tiles = array_values(array_filter(
            $manifest['tiles'],
            fn (array $tile) => isset($wanted[$tile['id']]) && $disk->exists("{$tileSet->directory()}/tif/{$tile['id']}.tif"),
        ));
        abort_if($tiles === [], 422, 'None of the selected tiles exist any more. Reload the page.');

        $source = $tileSet->source;
        $copied = [];
        try {
            $layer = DB::transaction(function () use ($request, $validated, $tiles, $tileSet, $source, $disk, &$copied) {
                $layer = DroneImageryLayer::create([
                    'user_id' => $request->user()->id,
                    'name' => Str::limit(trim((string) ($validated['name'] ?? '')) ?: $source->name, 120, ''),
                    'status' => 'queued',
                    'source_type' => count($tiles) > 1 ? 'batch' : 'single',
                ]);

                foreach ($tiles as $index => $tile) {
                    $from = "{$tileSet->directory()}/tif/{$tile['id']}.tif";
                    $storedPath = "drone-imagery/uploads/{$layer->id}/".Str::random(40).'.tif';
                    if (! $disk->copy($from, $storedPath)) {
                        throw new RuntimeException('Unable to copy a tile GeoTIFF.');
                    }
                    $copied[] = $storedPath;

                    $layer->files()->create([
                        'original_name' => Str::limit("{$tile['name']}.tif", 255, ''),
                        'stored_path' => $storedPath,
                        'mime_type' => 'image/tiff',
                        'file_size' => $disk->size($storedPath),
                        'sort_order' => $index,
                    ]);
                }

                return $layer;
            });
        } catch (Throwable $exception) {
            $disk->delete($copied);
            throw $exception;
        }

        $response = DroneImageryController::queueProcessing($layer, count($tiles));
        $response->setData($response->getData(true) + ['drone_upload_url' => route('drone-imagery.page')]);

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
