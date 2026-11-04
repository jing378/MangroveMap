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
    /** Edits that leave the mosaic untouched. */
    public const EMPTY_EDITS = ['mode' => 'remove', 'polygons' => [], 'offset' => ['east_m' => 0, 'north_m' => 0]];

    protected $fillable = [
        'user_id',
        'name',
        'status',
        'source_type',
        'source_crs',
        'bounds',
        'metadata',
        'mosaic_path',
        'preview_path',
        'preview_bounds',
        'edits',
        'applied_edits',
        'final_mosaic_path',
        'tiles_path',
        'zoom_min',
        'zoom_max',
        'display_opacity',
        'error_message',
        'processed_at',
        'reviewed_at',
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
            'preview_bounds' => 'array',
            'edits' => 'array',
            'applied_edits' => 'array',
            'metadata' => 'array',
            'display_opacity' => 'float',
            'processed_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'segmentation_stats' => 'array',
            'segmented_at' => 'datetime',
        ];
    }

    public function isSegmenting(): bool
    {
        return in_array($this->segmentation_status, ['queued', 'processing'], true);
    }

    /** A GDAL job is queued or running (preparing the preview, or applying edits and tiling). */
    public function isProcessing(): bool
    {
        return ! in_array($this->status, ['awaiting_review', 'ready', 'failed'], true);
    }

    /**
     * The owner can open the editor: a preview exists and no GDAL job is running.
     * Failed layers with a preview failed while finalizing, so they can be edited and confirmed again.
     */
    public function isReviewable(): bool
    {
        return $this->preview_path !== null
            && $this->preview_bounds !== null
            && in_array($this->status, ['awaiting_review', 'ready', 'failed'], true);
    }

    /** The owner corrected the AI mangrove areas; automatic re-runs must not replace them silently. */
    public function isSegmentationEdited(): bool
    {
        return $this->segmentation_status === 'ready' && (bool) ($this->segmentation_stats['edited'] ?? false);
    }

    public function currentEdits(): array
    {
        return $this->edits ?? self::EMPTY_EDITS;
    }

    /** The saved edits differ from the ones the current map tiles were built from. */
    public function hasPendingEdits(): bool
    {
        return $this->status === 'ready'
            && $this->currentEdits() != ($this->applied_edits ?? self::EMPTY_EDITS);
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
