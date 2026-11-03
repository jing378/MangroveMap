<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessDroneImagery;
use App\Jobs\SegmentDroneImagery;
use App\Models\DroneImageryLayer;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class DroneImageryController extends Controller
{
    /**
     * Fully transparent 1x1 RGBA PNG served for empty tiles inside a layer's bounds
     * (gdal2tiles --exclude skips them). Must have alpha 0, or Leaflet paints solid squares.
     */
    public const TRANSPARENT_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR4nGNgYGBgAAAABQABpfZFQAAAAABJRU5ErkJggg==';

    public function index(Request $request): JsonResponse
    {
        $viewer = $request->user();
        $layers = $this->visibleLayersQuery($viewer)
            ->whereDoesntHave('hiddenByUsers', fn (Builder $query) => $query->whereKey($viewer->id))
            ->with('user:id,name')
            ->latest()
            ->get();

        return response()->json([
            'layers' => $layers->map(fn (DroneImageryLayer $layer) => [
                'id' => $layer->id,
                'name' => $layer->name,
                'uploaded_by' => $layer->user?->name,
                'is_owner' => (int) $layer->user_id === (int) $viewer->id,
                'status' => $layer->status,
                'source_type' => $layer->source_type,
                'bounds' => $layer->status === 'ready' ? $layer->bounds : null,
                'zoom_min' => $layer->zoom_min,
                'zoom_max' => $layer->zoom_max,
                'tile_url' => $layer->status === 'ready'
                    ? url("/drone-imagery/{$layer->id}/tiles/{z}/{x}/{y}")
                    : null,
                'error_message' => $layer->error_message,
                ...$this->segmentationPayload($layer),
            ]),
        ]);
    }

    private function segmentationPayload(DroneImageryLayer $layer): array
    {
        return [
            'segmentation_status' => $layer->segmentation_status,
            'segmentation_stats' => $layer->segmentation_status === 'ready' ? $layer->segmentation_stats : null,
            'segmentation_error' => $layer->segmentation_status === 'failed' ? $layer->segmentation_error : null,
            'segmented_at' => $layer->segmented_at?->toIso8601String(),
            'segmentation_url' => $layer->segmentation_status === 'ready'
                ? route('drone-imagery.segmentation.show', $layer->id)
                : null,
        ];
    }

    /** AI-detected mangrove extent as delineation-ready area features. */
    public function segmentation(Request $request, int $layerId): JsonResponse
    {
        $viewer = $request->user();
        $layer = $this->visibleLayersQuery($viewer)
            ->where('status', 'ready')
            ->where('segmentation_status', 'ready')
            ->whereDoesntHave('hiddenByUsers', fn (Builder $query) => $query->whereKey($viewer->id))
            ->findOrFail($layerId);

        $disk = Storage::disk('local');
        if ($layer->segmentation_path !== "drone-imagery/processed/{$layer->id}/ai_extent.json"
            || ! $disk->exists($layer->segmentation_path)) {
            abort(404);
        }

        $result = json_decode((string) $disk->get($layer->segmentation_path), true);

        return response()->json([
            'id' => $layer->id,
            'name' => $layer->name,
            'features' => $result['features'] ?? [],
            'stats' => $result['stats'] ?? $layer->segmentation_stats,
            'segmented_at' => $layer->segmented_at?->toIso8601String(),
        ]);
    }

    /** Queue (or re-run) AI segmentation for a ready layer the viewer owns. */
    public function segment(Request $request, int $layerId): JsonResponse
    {
        $layer = DroneImageryLayer::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($layerId);

        if ($layer->status !== 'ready') {
            return response()->json([
                'message' => 'Wait until the imagery has finished processing before running AI segmentation.',
            ], 409);
        }

        if ($layer->isSegmenting()) {
            return response()->json([
                'message' => 'AI segmentation is already running for this layer.',
            ], 409);
        }

        $layer->update(['segmentation_status' => 'queued', 'segmentation_error' => null]);

        try {
            SegmentDroneImagery::dispatch($layer->id);
        } catch (Throwable $exception) {
            $layer->update([
                'segmentation_status' => 'failed',
                'segmentation_error' => 'Unable to queue AI segmentation. Please try again later.',
            ]);
            Log::error('Unable to queue drone imagery segmentation.', [
                'layer_id' => $layer->id,
                'exception' => $exception,
            ]);

            return response()->json([
                'message' => 'AI segmentation could not be queued.',
            ], 503);
        }

        return response()->json([
            'id' => $layer->id,
            'segmentation_status' => 'queued',
        ], 202);
    }

    private function visibleLayersQuery(User $viewer): Builder
    {
        return DroneImageryLayer::query()
            ->where(function ($query) use ($viewer) {
                // Admins manage every layer (Map Layers page), so they can preview any of them.
                if ($viewer->isAdmin()) {
                    return;
                }

                $query->where('user_id', $viewer->id);

                if ($viewer->isEndUser()) {
                    $query->orWhere(function ($sharedQuery) {
                        $sharedQuery->where('status', 'ready')
                            ->whereHas('user', fn ($userQuery) => $userQuery->where('role', 'expert'));
                    });
                }
            });
    }

    /** Effective files-per-batch limit: the app setting, capped by PHP's max_file_uploads. */
    public static function maxUploadFiles(): int
    {
        $phpLimit = (int) ini_get('max_file_uploads');
        $appLimit = max(1, (int) config('gdal.max_upload_files', 100));

        return $phpLimit > 0 ? min($appLimit, $phpLimit) : $appLimit;
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'files' => ['required', 'array', 'min:1', 'max:'.self::maxUploadFiles()],
            'files.*' => ['required', 'file', 'extensions:tif,tiff', 'max:1048576'],
        ]);

        $files = $request->file('files');
        $disk = Storage::disk('local');
        $storedPaths = [];

        try {
            $layer = DB::transaction(function () use ($request, $validated, $files, &$storedPaths) {
                $layer = DroneImageryLayer::create([
                    'user_id' => $request->user()->id,
                    'name' => $validated['name'] ?? 'Drone imagery',
                    'status' => 'queued',
                    'source_type' => count($files) > 1 ? 'batch' : 'single',
                ]);

                foreach ($files as $index => $file) {
                    $directory = "drone-imagery/uploads/{$layer->id}";
                    $storedPath = $file->store($directory, 'local');
                    if ($storedPath === false) {
                        throw new RuntimeException('Unable to save an uploaded TIFF file.');
                    }

                    $storedPaths[] = $storedPath;
                    $layer->files()->create([
                        'original_name' => Str::limit($file->getClientOriginalName(), 255, ''),
                        'stored_path' => $storedPath,
                        'mime_type' => $file->getMimeType(),
                        'file_size' => $file->getSize(),
                        'sort_order' => $index,
                    ]);
                }

                return $layer;
            });
        } catch (Throwable $exception) {
            $disk->delete($storedPaths);
            throw $exception;
        }

        try {
            ProcessDroneImagery::dispatch($layer->id);
        } catch (Throwable $exception) {
            $layer->update([
                'status' => 'failed',
                'error_message' => 'Unable to queue raster processing. Please try again later.',
            ]);
            Log::error('Unable to queue drone imagery processing.', [
                'layer_id' => $layer->id,
                'exception' => $exception,
            ]);

            return response()->json([
                'message' => 'The TIFF files were uploaded but could not be queued for processing.',
                'id' => $layer->id,
                'status' => 'failed',
            ], 503);
        }

        return response()->json([
            'id' => $layer->id,
            'name' => $layer->name,
            'status' => $layer->status,
            'source_type' => $layer->source_type,
            'file_count' => count($files),
        ], 202);
    }

    public function show(Request $request, int $layerId): JsonResponse
    {
        $layer = DroneImageryLayer::query()
            ->where('user_id', $request->user()->id)
            ->with('files:id,drone_imagery_layer_id,original_name,file_size,sort_order')
            ->findOrFail($layerId);

        return response()->json([
            'id' => $layer->id,
            'name' => $layer->name,
            'status' => $layer->status,
            'source_type' => $layer->source_type,
            'files' => $layer->files->map(fn ($file) => [
                'name' => $file->original_name,
                'size' => $file->file_size,
            ]),
            'error_message' => $layer->error_message,
            ...$this->segmentationPayload($layer),
        ]);
    }

    public function destroy(Request $request, int $layerId): JsonResponse
    {
        $viewer = $request->user();
        $layer = DroneImageryLayer::query()->with('user')->findOrFail($layerId);

        if ((int) $layer->user_id !== (int) $viewer->id) {
            if (! $viewer->isEndUser() || $layer->status !== 'ready' || ! $layer->user?->isExpert()) {
                abort(404);
            }

            $layer->hiddenByUsers()->syncWithoutDetaching([$viewer->id]);

            return response()->json(['action' => 'hidden']);
        }

        if ($layer->isProcessing()) {
            return response()->json([
                'message' => 'Wait until TIFF processing finishes before deleting this layer.',
            ], 409);
        }

        if ($layer->isSegmenting()) {
            return response()->json([
                'message' => 'Wait until AI segmentation finishes before deleting this layer.',
            ], 409);
        }

        $layer->deleteWithStoredFiles();

        return response()->json(['action' => 'deleted']);
    }

    public function tile(Request $request, int $layerId, int $z, int $x, int $y): Response|BinaryFileResponse
    {
        $viewer = $request->user();
        $layer = $this->visibleLayersQuery($viewer)
            ->where('status', 'ready')
            ->whereDoesntHave('hiddenByUsers', fn (Builder $query) => $query->whereKey($viewer->id))
            ->findOrFail($layerId);

        if (
            $z < $layer->zoom_min || $z > $layer->zoom_max
            || $x < 0 || $y < 0
            || $layer->tiles_path !== "drone-imagery/tiles/{$layer->id}"
        ) {
            abort(404);
        }

        $tileCount = 2 ** $z;
        if ($x >= $tileCount || $y >= $tileCount) {
            abort(404);
        }

        $tilePath = Storage::disk('local')->path("{$layer->tiles_path}/{$z}/{$x}/{$y}.png");
        if (is_file($tilePath)) {
            $fileResponse = response()->file($tilePath);
            $fileResponse->headers->set('Content-Type', 'image/png');
            $fileResponse->headers->set('Cache-Control', 'private, max-age=86400');
            $fileResponse->headers->set('X-Content-Type-Options', 'nosniff');

            return $fileResponse;
        }

        return response(base64_decode(self::TRANSPARENT_PNG, true), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
