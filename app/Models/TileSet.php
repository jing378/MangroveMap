<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** A TileSource cut into equal square tiles of one size by GenerateTiles (scripts/gdal/make_tiles.py). */
class TileSet extends Model
{
    /** Tile edge lengths (px) offered; 640 is Roboflow's/YOLO's default input size. */
    public const TILE_SIZES = [256, 512, 640, 1024, 1280];

    public const DEFAULT_TILE_SIZE = 640;

    protected $fillable = [
        'tile_source_id',
        'tile_size',
        'status',
        'tile_count',
        'mangrove_tile_count',
        'columns',
        'rows',
        'error_message',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'tile_size' => 'integer',
            'generated_at' => 'datetime',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(TileSource::class, 'tile_source_id');
    }

    public function directory(): string
    {
        return "tile-explorer/{$this->tile_source_id}/tiles/{$this->tile_size}";
    }

    public function manifestPath(): string
    {
        return $this->directory().'/tiles.json';
    }

    public function isBusy(): bool
    {
        return in_array($this->status, ['queued', 'detecting', 'processing'], true);
    }

    /** Tile list from tiles.json ({tile_size, columns, rows, crs, pixel_size, has_areas, tiles[]}), or null. */
    public function manifest(): ?array
    {
        $raw = Storage::disk('local')->get($this->manifestPath());
        $manifest = $raw === null ? null : json_decode($raw, true);

        return is_array($manifest) && is_array($manifest['tiles'] ?? null) ? $manifest : null;
    }

    /** {done, total} written by the script while it cuts tiles. */
    public function progress(): ?array
    {
        $raw = Storage::disk('local')->get($this->directory().'/progress.json');
        $progress = $raw === null ? null : json_decode($raw, true);

        return is_array($progress) ? $progress : null;
    }
}
