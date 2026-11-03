<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DroneImageryLayer extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'status',
        'source_type',
        'source_crs',
        'bounds',
        'metadata',
        'mosaic_path',
        'tiles_path',
        'zoom_min',
        'zoom_max',
        'error_message',
        'processed_at',
        'segmentation_status',
        'segmentation_path',
        'segmentation_stats',
        'segmentation_error',
        'segmented_at',
    ];

    protected function casts(): array
    {
        return [
            'bounds' => 'array',
            'metadata' => 'array',
            'processed_at' => 'datetime',
            'segmentation_stats' => 'array',
            'segmented_at' => 'datetime',
        ];
    }

    public function isSegmenting(): bool
    {
        return in_array($this->segmentation_status, ['queued', 'processing'], true);
    }

    public function isProcessing(): bool
    {
        return ! in_array($this->status, ['ready', 'failed'], true);
    }

    /** Remove the uploads, tiles and processed outputs from the private disk, then the row. */
    public function deleteWithStoredFiles(): void
    {
        $disk = Storage::disk('local');
        foreach ([
            "drone-imagery/uploads/{$this->id}",
            "drone-imagery/tiles/{$this->id}",
            "drone-imagery/processed/{$this->id}",
        ] as $directory) {
            if (! $disk->deleteDirectory($directory)) {
                throw new RuntimeException("Unable to remove stored imagery directory: {$directory}");
            }
        }

        $this->delete();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(DroneImageryFile::class)->orderBy('sort_order');
    }

    public function hiddenByUsers(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'drone_imagery_layer_hidden_users',
            'drone_imagery_layer_id',
            'user_id'
        )->withTimestamps();
    }
}
