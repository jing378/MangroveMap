<?php

namespace App\Http\Controllers;

use App\Jobs\FinalizeDroneImagery;
use App\Jobs\ProcessDroneImagery;
use App\Jobs\SegmentDroneImagery;
use App\Models\DroneImageryLayer;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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

    /** Review editor limits: masking polygons per layer, their total vertices, and the position nudge. */
    public const MAX_EDIT_POLYGONS = 100;

    public const MAX_EDIT_VERTICES = 20000;

    public const MAX_OFFSET_METRES = 50;

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
                'display_opacity' => $layer->display_opacity,
                'processed_at' => $layer->processed_at?->toIso8601String(),
                'created_at' => $layer->created_at?->toIso8601String(),
                'error_message' => $layer->error_message,
                ...$this->reviewPayload($layer, $viewer),
                ...$this->segmentationPayload($layer),
            ]),
        ]);
    }

    private function reviewPayload(DroneImageryLayer $layer, User $viewer): array
    {
        $isOwner = (int) $layer->user_id === (int) $viewer->id;

        return [
            'review_url' => $isOwner && $layer->isReviewable() ? route('drone-imagery.edit', $layer->id) : null,
            'has_pending_edits' => $isOwner && $layer->hasPendingEdits(),
        ];
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
        // awaiting_review layers only match for their owner (shared layers must be ready), for the review editor.
        $layer = $this->visibleLayersQuery($viewer)
            ->whereIn('status', ['ready', 'awaiting_review'])
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

    /** Queue (or re-run) AI segmentation for a ready or under-review layer the viewer owns. */
    public function segment(Request $request, int $layerId): JsonResponse
    {
        $layer = DroneImageryLayer::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($layerId);

        if (! in_array($layer->status, ['ready', 'awaiting_review'], true)) {
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
            ...$this->reviewPayload($layer, $request->user()),
            ...$this->segmentationPayload($layer),
        ]);
    }

    /** Drone Upload tab: upload GeoTIFFs and follow/manage the viewer's own imagery (data comes from index()). */
    public function page(Request $request): View
    {
        $viewer = $request->user();
        DroneImageryLayer::query()
            ->where('user_id', $viewer->id)
            ->where('status', 'awaiting_review')
            ->whereNull('segmentation_status')
            ->get()
            ->each(fn (DroneImageryLayer $layer) => $this->queueMissingSegmentation($layer));

        return view('users.drone-upload', [
            'page' => [
                'index_url' => route('drone-imagery.index'),
                'upload_url' => route('drone-imagery.store'),
                'layer_url' => url('/drone-imagery'),
                'map_url' => $viewer->isExpert() ? route('expert.delineate') : route('delineate'),
                'max_files' => self::maxUploadFiles(),
                'highlight_id' => $request->integer('layer') ?: null,
            ],
        ]);
    }

    /** Review editor: preview the prepared mosaic over the base map, mask out non-mangrove areas, adjust and confirm. */
    public function edit(Request $request, int $layerId): View|RedirectResponse
    {
        $viewer = $request->user();
        $layer = DroneImageryLayer::query()
            ->where('user_id', $viewer->id)
            ->findOrFail($layerId);

        if (! $layer->isReviewable()) {
            return redirect()->route('drone-imagery.page', ['layer' => $layer->id]);
        }

        $this->queueMissingSegmentation($layer);

        return view('users.drone-imagery-editor', [
            'layer' => $layer,
            'editor' => [
                'id' => $layer->id,
                'name' => $layer->name,
                'status' => $layer->status,
                'error_message' => $layer->status === 'failed' ? $layer->error_message : null,
                'preview_url' => route('drone-imagery.preview', $layer->id),
                'preview_bounds' => $layer->preview_bounds,
                'edits' => $layer->currentEdits(),
                'opacity' => $layer->display_opacity,
                'has_pending_edits' => $layer->hasPendingEdits(),
                ...$this->segmentationPayload($layer),
                'status_url' => route('drone-imagery.show', $layer->id),
                'segmentation_update_url' => route('drone-imagery.segmentation.update', $layer->id),
                'segment_url' => route('drone-imagery.segmentation.store', $layer->id),
                'save_url' => route('drone-imagery.edits.update', $layer->id),
                'confirm_url' => route('drone-imagery.confirm', $layer->id),
                'back_url' => route('drone-imagery.page', ['layer' => $layer->id]),
                'max_polygons' => self::MAX_EDIT_POLYGONS,
                'max_offset_m' => self::MAX_OFFSET_METRES,
            ],
        ]);
    }

    public function preview(Request $request, int $layerId): BinaryFileResponse
    {
        $layer = DroneImageryLayer::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($layerId);

        $disk = Storage::disk('local');
        if ($layer->preview_path !== "drone-imagery/processed/{$layer->id}/preview.png" || ! $disk->exists($layer->preview_path)) {
            abort(404);
        }

        $response = response()->file($disk->path($layer->preview_path));
        $response->headers->set('Content-Type', 'image/png');
        // Short cache: re-processing the upload replaces the preview under the same URL.
        $response->headers->set('Cache-Control', 'private, max-age=300');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }

    /** Save the editor's draft. Opacity applies to the map straight away; mask and position need confirm(). */
    public function updateEdits(Request $request, int $layerId): JsonResponse
    {
        $layer = $this->reviewableLayer($request, $layerId);
        if ($layer instanceof JsonResponse) {
            return $layer;
        }

        [$edits, $opacity] = $this->validatedEdits($request, $layer);
        $layer->update(['edits' => $edits, 'display_opacity' => $opacity]);

        return response()->json([
            'id' => $layer->id,
            'status' => $layer->status,
            'has_pending_edits' => $layer->hasPendingEdits(),
        ]);
    }

    /** Save the edits and queue FinalizeDroneImagery to apply them and tile the imagery for the map. */
    public function confirm(Request $request, int $layerId): JsonResponse
    {
        $layer = $this->reviewableLayer($request, $layerId);
        if ($layer instanceof JsonResponse) {
            return $layer;
        }

        if ($layer->isSegmenting()) {
            return response()->json([
                'message' => 'Wait until AI segmentation finishes before applying new edits to this layer.',
            ], 409);
        }

        [$edits, $opacity] = $this->validatedEdits($request, $layer);
        if ($edits['mode'] !== 'remove' && $edits['polygons'] === []) {
            throw ValidationException::withMessages([
                'polygons' => $edits['mode'] === 'keep_ai'
                    ? 'There are no mangrove areas to crop the imagery to. Draw some, or choose another option.'
                    : 'Draw at least one area to keep, or switch to "Remove areas".',
            ]);
        }

        $layer->update([
            'edits' => $edits,
            'display_opacity' => $opacity,
            'status' => 'finalizing',
            'error_message' => null,
        ]);

        try {
            FinalizeDroneImagery::dispatch($layer->id);
        } catch (Throwable $exception) {
            $layer->update([
                'status' => 'failed',
                'error_message' => 'Unable to queue the imagery for the map. Please try again later.',
            ]);
            Log::error('Unable to queue drone imagery finalizing.', [
                'layer_id' => $layer->id,
                'exception' => $exception,
            ]);

            return response()->json(['message' => 'The edits were saved but could not be queued for processing.'], 503);
        }

        return response()->json([
            'id' => $layer->id,
            'status' => $layer->fresh()->status,
            'redirect_url' => route('drone-imagery.page', ['layer' => $layer->id]),
        ], 202);
    }

    /**
     * The review relies on the AI delineation. ProcessDroneImagery queues it, but a layer can end up under
     * review without it (e.g. prepared by a queue worker still running older code), so queue it here.
     * A failed run is not retried automatically: the editor offers "Retry AI detection".
     */
    private function queueMissingSegmentation(DroneImageryLayer $layer): void
    {
        if (
            ! config('ml.drone.auto_segment')
            || $layer->status !== 'awaiting_review'
            || $layer->segmentation_status !== null
            || ! $layer->mosaic_path
            || ! Storage::disk('local')->exists($layer->mosaic_path)
        ) {
            return;
        }

        $layer->update(['segmentation_status' => 'queued', 'segmentation_error' => null]);
        try {
            SegmentDroneImagery::dispatch($layer->id);
        } catch (Throwable $exception) {
            $layer->update([
                'segmentation_status' => 'failed',
                'segmentation_error' => 'Unable to queue AI detection. Please try again later.',
            ]);
            Log::error('Unable to queue missing drone imagery segmentation.', [
                'layer_id' => $layer->id,
                'exception' => $exception,
            ]);
        }
    }

    private function reviewableLayer(Request $request, int $layerId): DroneImageryLayer|JsonResponse
    {
        $layer = DroneImageryLayer::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($layerId);

        if (! $layer->isReviewable()) {
            return response()->json([
                'message' => $layer->isProcessing()
                    ? 'This imagery is still being processed. Try again when it has finished.'
                    : 'This imagery has no preview to edit. Upload it again.',
            ], 409);
        }

        return $layer;
    }

    /**
     * @return array{0: array{mode: string, polygons: array, offset: array{east_m: float, north_m: float}}, 1: float}
     */
    private function validatedEdits(Request $request, DroneImageryLayer $layer): array
    {
        $validated = $request->validate([
            // keep_ai: crop to the AI mangrove areas (the editor sends them as the polygons).
            'mode' => ['required', 'in:remove,keep,keep_ai'],
            'polygons' => ['present', 'array', 'max:'.self::MAX_EDIT_POLYGONS],
            'offset' => ['required', 'array'],
            'offset.east_m' => ['required', 'numeric', 'between:-'.self::MAX_OFFSET_METRES.','.self::MAX_OFFSET_METRES],
            'offset.north_m' => ['required', 'numeric', 'between:-'.self::MAX_OFFSET_METRES.','.self::MAX_OFFSET_METRES],
            'opacity' => ['required', 'numeric', 'between:0.1,1'],
        ]);

        return [[
            'mode' => $validated['mode'],
            'polygons' => $this->validatedRings($validated['polygons'], $layer, 'polygons'),
            'offset' => [
                'east_m' => round((float) $validated['offset']['east_m'], 2),
                'north_m' => round((float) $validated['offset']['north_m'], 2),
            ],
        ], round((float) $validated['opacity'], 2)];
    }

    /**
     * Check WGS84 [lng, lat] rings by hand (per-vertex validation rules are far too slow for detailed
     * shapes): enough points, inside the imagery (with room for the position offset), vertex budget.
     *
     * @return array<int, array<int, array{0: float, 1: float}>> unclosed rings rounded to 7 decimals
     */
    private function validatedRings(array $rings, DroneImageryLayer $layer, string $field): array
    {
        $bounds = $layer->preview_bounds;
        $margin = 0.01; // ~1 km, more than the largest allowed position offset
        $vertexCount = 0;
        $polygons = [];
        foreach ($rings as $index => $ring) {
            $label = 'Area '.($index + 1);
            if (! is_array($ring) || ! array_is_list($ring)) {
                throw ValidationException::withMessages([$field => "{$label} is not a list of points."]);
            }

            $points = [];
            foreach ($ring as $point) {
                if (! is_array($point) || count($point) !== 2 || ! is_numeric($point[0] ?? null) || ! is_numeric($point[1] ?? null)) {
                    throw ValidationException::withMessages([$field => "{$label} has an invalid point."]);
                }
                $lng = round((float) $point[0], 7);
                $lat = round((float) $point[1], 7);
                if (
                    $lng < $bounds['west'] - $margin || $lng > $bounds['east'] + $margin
                    || $lat < $bounds['south'] - $margin || $lat > $bounds['north'] + $margin
                ) {
                    throw ValidationException::withMessages([$field => "{$label} is outside the imagery."]);
                }
                $points[] = [$lng, $lat];
            }

            if (count($points) > 1 && $points[0] === end($points)) {
                array_pop($points);
            }
            if (count($points) < 3) {
                throw ValidationException::withMessages([$field => "{$label} needs at least 3 points."]);
            }

            $vertexCount += count($points);
            if ($vertexCount > self::MAX_EDIT_VERTICES) {
                throw ValidationException::withMessages([
                    $field => 'The areas have too many points (max '.self::MAX_EDIT_VERTICES.'). Simplify some shapes.',
                ]);
            }
            $polygons[] = $points;
        }

        return $polygons;
    }

    /**
     * Save the owner's corrections to the AI mangrove areas (removed, reshaped or added areas). They replace
     * the AI result everywhere it is used (map AI extent, "Use as delineation", statistics) and are kept when
     * the imagery is confirmed, until the owner runs the AI again.
     */
    public function updateSegmentation(Request $request, int $layerId): JsonResponse
    {
        $layer = $this->reviewableLayer($request, $layerId);
        if ($layer instanceof JsonResponse) {
            return $layer;
        }

        if ($layer->isSegmenting()) {
            return response()->json([
                'message' => 'Wait until AI detection finishes before editing the mangrove areas.',
            ], 409);
        }

        $validated = $request->validate([
            'areas' => ['present', 'array', 'max:'.self::MAX_EDIT_POLYGONS],
            'offset' => ['required', 'array'],
            'offset.east_m' => ['required', 'numeric', 'between:-'.self::MAX_OFFSET_METRES.','.self::MAX_OFFSET_METRES],
            'offset.north_m' => ['required', 'numeric', 'between:-'.self::MAX_OFFSET_METRES.','.self::MAX_OFFSET_METRES],
        ]);
        $rings = $this->validatedRings($validated['areas'], $layer, 'areas');

        $latitude = (((float) $layer->preview_bounds['south']) + ((float) $layer->preview_bounds['north'])) / 2;
        $features = [];
        $areaM2 = 0.0;
        foreach ($rings as $ring) {
            $areaM2 += $this->ringAreaM2($ring, $latitude);
            // Stored like SegmentationFeatureBuilder output: delineation areas in Leaflet [lat, lng] order.
            $features[] = ['type' => 'area', 'coords' => array_map(fn (array $point) => [$point[1], $point[0]], $ring)];
        }

        $previous = $layer->segmentation_stats ?? [];
        $areaHa = round($areaM2 / 10000, 3);
        $surveyedHa = (float) ($previous['surveyed_area_ha'] ?? 0);
        $stats = [
            ...$previous,
            'mangrove_area_ha' => $areaHa,
            'feature_area_ha' => $areaHa,
            'feature_count' => count($features),
            'polygon_count' => count($features),
            'mangrove_coverage_percent' => $surveyedHa > 0 ? round(min(100, $areaHa / $surveyedHa * 100), 2) : 0,
            'offset' => [
                'east_m' => round((float) $validated['offset']['east_m'], 2),
                'north_m' => round((float) $validated['offset']['north_m'], 2),
            ],
            'edited' => true,
            'edited_at' => now()->toIso8601String(),
        ];

        $path = "drone-imagery/processed/{$layer->id}/ai_extent.json";
        Storage::disk('local')->put($path, json_encode(['features' => $features, 'stats' => $stats], JSON_UNESCAPED_SLASHES));
        $layer->update([
            'segmentation_status' => 'ready',
            'segmentation_path' => $path,
            'segmentation_stats' => $stats,
            'segmentation_error' => null,
            'segmented_at' => now(), // also the cache key the map uses for the AI extent
        ]);

        return response()->json([
            'id' => $layer->id,
            ...$this->segmentationPayload($layer),
        ]);
    }

    /** Planar ring area at survey scale (local equirectangular projection around $latitude). */
    private function ringAreaM2(array $ring, float $latitude): float
    {
        $metresPerDegree = 111319.49;
        $cos = cos(deg2rad($latitude));
        $twice = 0.0;
        $count = count($ring);
        foreach ($ring as $index => [$lng, $lat]) {
            [$nextLng, $nextLat] = $ring[($index + 1) % $count];
            $twice += ($lng * $cos * $metresPerDegree) * ($nextLat * $metresPerDegree)
                - ($nextLng * $cos * $metresPerDegree) * ($lat * $metresPerDegree);
        }

        return abs($twice) / 2;
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
