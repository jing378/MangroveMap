<?php

namespace App\Http\Controllers;

use App\Models\DroneImageryLayer;
use App\Models\TileSet;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Chunked, resumable GeoTIFF upload (Drone Upload tab).
 *
 * 1. start():    the browser announces the files; a session folder drone-imagery/incoming/{uuid} is made.
 * 2. chunk():    each slice of a file is PUT as a raw body and written straight to its offset in the
 *                file's .part, so slices can arrive in parallel and in any order, and no reassembly
 *                or extra copy is needed at the end. A marker file records each received slice.
 * 3. status():   lists the received slices, so an interrupted upload resumes where it stopped.
 * 4. complete(): checks every slice is there, moves the .part files into place and queues processing
 *                exactly like DroneImageryController::store().
 *
 * The same flow uploads a single GeoTIFF for the Tile Explorer (purpose "tiles").
 *
 * Abandoned sessions are removed after SESSION_TTL_HOURS (on every start() and by drone-imagery:prune-uploads).
 */
class DroneImageryUploadController extends Controller
{
    public const INCOMING_DIRECTORY = 'drone-imagery/incoming';

    public const SESSION_TTL_HOURS = 24;

    public const MAX_FILE_BYTES = 1024 * 1024 * 1024; // matches store()'s max:1048576 (KB)

    public function start(Request $request): JsonResponse
    {
        // purpose "tiles": one GeoTIFF for the Tile Explorer (TileExplorerController), not a drone survey.
        $forTiles = $request->input('purpose') === 'tiles';
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'purpose' => ['nullable', 'in:drone,tiles'],
            'tile_size' => [$forTiles ? 'required' : 'nullable', 'integer', 'in:'.implode(',', TileSet::TILE_SIZES)],
            'files' => ['required', 'array', 'min:1', 'max:'.($forTiles ? 1 : DroneImageryController::maxUploadFiles())],
            'files.*.name' => ['required', 'string', 'max:255', 'regex:/\.tiff?$/i'],
            'files.*.size' => ['required', 'integer', 'min:1', 'max:'.self::MAX_FILE_BYTES],
        ], [
            'files.*.name.regex' => 'Only .tif or .tiff files can be uploaded.',
            'files.*.size.max' => 'Each file can be at most 1 GB.',
        ]);

        self::pruneStaleSessions();

        $id = (string) Str::uuid();
        $chunkSize = self::chunkSize();
        $manifest = [
            'user_id' => $request->user()->id,
            'name' => $validated['name'] ?? null,
            'purpose' => $forTiles ? 'tiles' : 'drone',
            'tile_size' => $forTiles ? (int) $validated['tile_size'] : null,
            'chunk_size' => $chunkSize,
            'created_at' => now()->getTimestamp(),
            'files' => array_map(fn (array $file) => [
                'name' => Str::limit($file['name'], 255, ''),
                'size' => (int) $file['size'],
                'chunks' => (int) ceil($file['size'] / $chunkSize),
            ], array_values($validated['files'])),
        ];

        $disk = $this->disk();
        if (! $disk->makeDirectory($this->sessionPath($id).'/received')
            || ! $disk->put($this->sessionPath($id).'/manifest.json', json_encode($manifest))) {
            throw new RuntimeException('Unable to start the upload.');
        }

        return response()->json(['upload_id' => $id, 'chunk_size' => $chunkSize], 201);
    }

    public function status(Request $request, string $uploadId): JsonResponse
    {
        $manifest = $this->manifest($request, $uploadId);

        return response()->json([
            'upload_id' => $uploadId,
            'chunk_size' => $manifest['chunk_size'],
            'received' => $this->receivedChunks($uploadId, count($manifest['files'])),
        ]);
    }

    public function chunk(Request $request, string $uploadId, int $fileIndex, int $chunkIndex): JsonResponse
    {
        $manifest = $this->manifest($request, $uploadId);
        $file = $manifest['files'][$fileIndex] ?? null;
        if ($file === null || $chunkIndex >= $file['chunks']) {
            abort(404);
        }

        $chunkSize = $manifest['chunk_size'];
        $offset = $chunkIndex * $chunkSize;
        $expected = min($chunkSize, $file['size'] - $offset);

        $disk = $this->disk();
        $partPath = $disk->path($this->sessionPath($uploadId)."/{$fileIndex}.part");
        $written = $this->writeAt($partPath, $offset, $request->getContent(true), $expected);
        if ($written !== $expected) {
            return response()->json([
                'message' => 'A piece of the file arrived damaged or incomplete. It will be sent again.',
            ], 422);
        }

        $disk->put($this->markerPath($uploadId, $fileIndex, $chunkIndex), '');

        return response()->json(['received' => true]);
    }

    public function complete(Request $request, string $uploadId): JsonResponse
    {
        $manifest = $this->manifest($request, $uploadId);
        $received = $this->receivedChunks($uploadId, count($manifest['files']));
        $disk = $this->disk();
        $session = $this->sessionPath($uploadId);

        foreach ($manifest['files'] as $index => $file) {
            $partPath = "{$session}/{$index}.part";
            if (count($received[$index]) !== $file['chunks'] || ! $disk->exists($partPath) || $disk->size($partPath) !== $file['size']) {
                throw ValidationException::withMessages([
                    'files' => "\"{$file['name']}\" has not finished uploading. Try the upload again to send the missing parts.",
                ]);
            }
        }

        if (($manifest['purpose'] ?? 'drone') === 'tiles') {
            $response = TileExplorerController::createFromUpload(
                $request->user(), $manifest['name'], $manifest['files'][0], "{$session}/0.part", (int) $manifest['tile_size'],
            );
            $disk->deleteDirectory($session);

            return $response;
        }

        $movedPaths = [];
        try {
            $layer = DB::transaction(function () use ($request, $manifest, $disk, $session, &$movedPaths) {
                $files = $manifest['files'];
                $layer = DroneImageryLayer::create([
                    'user_id' => $request->user()->id,
                    'name' => $manifest['name'] ?: 'Drone imagery',
                    'status' => 'queued',
                    'source_type' => count($files) > 1 ? 'batch' : 'single',
                ]);

                foreach ($files as $index => $file) {
                    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) === 'tiff' ? 'tiff' : 'tif';
                    $storedPath = "drone-imagery/uploads/{$layer->id}/".Str::random(40).".{$extension}";
                    // Same disk: a rename, not a copy, so even 1 GB files are moved instantly.
                    if (! $disk->move("{$session}/{$index}.part", $storedPath)) {
                        throw new RuntimeException('Unable to save an uploaded TIFF file.');
                    }
                    $movedPaths[] = $storedPath;

                    $layer->files()->create([
                        'original_name' => $file['name'],
                        'stored_path' => $storedPath,
                        'mime_type' => 'image/tiff',
                        'file_size' => $file['size'],
                        'sort_order' => $index,
                    ]);
                }

                return $layer;
            });
        } catch (Throwable $exception) {
            $disk->delete($movedPaths);
            throw $exception;
        }

        $disk->deleteDirectory($session);

        return DroneImageryController::queueProcessing($layer, count($manifest['files']));
    }

    public function cancel(Request $request, string $uploadId): JsonResponse
    {
        $this->manifest($request, $uploadId);
        $this->disk()->deleteDirectory($this->sessionPath($uploadId));

        return response()->json(['cancelled' => true]);
    }

    /** Deletes upload sessions nobody touched for SESSION_TTL_HOURS. Returns how many were removed. */
    public static function pruneStaleSessions(): int
    {
        $disk = Storage::disk('local');
        $cutoff = now()->subHours(self::SESSION_TTL_HOURS)->getTimestamp();
        $removed = 0;

        foreach ($disk->directories(self::INCOMING_DIRECTORY) as $directory) {
            $lastActivity = collect($disk->allFiles($directory))
                ->map(fn (string $path) => $disk->lastModified($path))
                ->push(0)
                ->max();
            if ($lastActivity < $cutoff) {
                $disk->deleteDirectory($directory);
                $removed++;
            }
        }

        return $removed;
    }

    public static function chunkSize(): int
    {
        return max(1, (int) config('gdal.upload_chunk_mb', 8)) * 1024 * 1024;
    }

    /**
     * Streams the request body into $path at $offset (creating the file if needed), never past the slice.
     * Returns the bytes written, or -1 when the body was longer than the slice (the client sent something else).
     */
    private function writeAt(string $path, int $offset, mixed $body, int $limit): int
    {
        if (! is_resource($body)) {
            $stream = fopen('php://temp', 'w+b');
            fwrite($stream, (string) $body);
            rewind($stream);
            $body = $stream;
        }

        $handle = fopen($path, 'c');
        if ($handle === false) {
            throw new RuntimeException('Unable to write the uploaded file.');
        }

        try {
            fseek($handle, $offset);
            $written = (int) stream_copy_to_stream($body, $handle, $limit);
        } finally {
            fclose($handle);
        }

        return fread($body, 1) === '' ? $written : -1;
    }

    /** @return array<int, list<int>> received chunk numbers per file index */
    private function receivedChunks(string $uploadId, int $fileCount): array
    {
        $received = array_fill(0, $fileCount, []);
        foreach ($this->disk()->files($this->sessionPath($uploadId).'/received') as $marker) {
            if (preg_match('/^(\d+)-(\d+)$/', basename($marker), $match) && isset($received[(int) $match[1]])) {
                $received[(int) $match[1]][] = (int) $match[2];
            }
        }

        return array_map(function (array $chunks) {
            sort($chunks);

            return $chunks;
        }, $received);
    }

    /** The session's manifest; 404 when it is missing or belongs to someone else. */
    private function manifest(Request $request, string $uploadId): array
    {
        $raw = $this->disk()->get($this->sessionPath($uploadId).'/manifest.json');
        $manifest = $raw === null ? null : json_decode($raw, true);
        if (! is_array($manifest) || ($manifest['user_id'] ?? null) !== $request->user()->id) {
            abort(404, 'This upload has expired or does not exist. Start the upload again.');
        }

        return $manifest;
    }

    private function sessionPath(string $uploadId): string
    {
        return self::INCOMING_DIRECTORY.'/'.$uploadId;
    }

    private function markerPath(string $uploadId, int $fileIndex, int $chunkIndex): string
    {
        return $this->sessionPath($uploadId)."/received/{$fileIndex}-{$chunkIndex}";
    }

    private function disk(): Filesystem
    {
        return Storage::disk('local');
    }
}
