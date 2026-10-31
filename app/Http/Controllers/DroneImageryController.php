<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessDroneImagery;
use App\Models\DroneImageryLayer;
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
    public function index(Request $request): JsonResponse
    {
        $layers = DroneImageryLayer::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->get();

        return response()->json([
            'layers' => $layers->map(fn (DroneImageryLayer $layer) => [
                'id' => $layer->id,
                'name' => $layer->name,
                'status' => $layer->status,
                'source_type' => $layer->source_type,
                'bounds' => $layer->status === 'ready' ? $layer->bounds : null,
                'zoom_min' => $layer->zoom_min,
                'zoom_max' => $layer->zoom_max,
                'tile_url' => $layer->status === 'ready'
                    ? url("/drone-imagery/{$layer->id}/tiles/{z}/{x}/{y}")
                    : null,
                'error_message' => $layer->error_message,
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'files' => ['required', 'array', 'min:1', 'max:20'],
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
        ]);
    }

    public function tile(Request $request, int $layerId, int $z, int $x, int $y): Response|BinaryFileResponse
    {
        $layer = DroneImageryLayer::query()
            ->where('user_id', $request->user()->id)
            ->where('status', 'ready')
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

        $transparentPixel = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/pWQAAAAASUVORK5CYII=', true);

        return response($transparentPixel, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
