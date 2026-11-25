<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class DroneImageryLayer extends Model
{
    /** Edits that leave the mosaic untouched. */
    public const EMPTY_EDITS = ['mode' => 'remove', 'polygons' => [], 'offset' => ['east_m' => 0, 'north_m' => 0]];

    /**
     * What the genus classification (ClassifyDroneImagery) adds to the AI results, kept in classification_stats.
     * Payloads still show them inside segmentation_stats (see aiStats), where they lived before the split.
     */
    public const CLASSIFICATION_STAT_KEYS = ['genus_suggestion', 'canopy_count', 'canopies_by_genus'];

    /**
     * Where a layer is on its way to the map, in the order it gets there (admin Map Layers "Stage").
     * waiting_owner: processed, the AI is done (or could not run) and the owner has not confirmed the areas yet.
     */
    public const STAGES = [
        'processing' => 'Processing',
        'detecting' => 'AI detecting areas',
        'waiting_owner' => 'Waiting for owner',
        'finalizing' => 'Finalizing',
        'pending_expert' => 'Pending expert review',
        'rejected' => 'Rejected by expert',
        'published' => 'Published',
        'not_submitted' => 'Ready, not submitted',
        'failed' => 'Failed',
    ];

    /** The AI's areas for a layer waiting for its owner become a "stale draft" after this many days. */
    public const STALE_DRAFT_DAYS = 7;

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
        'classification_status',
        'classification_stats',
        'classification_error',
        'classified_at',
        'delineation_details',
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
            'classification_stats' => 'array',
            'classified_at' => 'datetime',
            'delineation_details' => 'array',
        ];
    }

    /**
     * Shared on everyone's map: ready, and either uploaded by an expert (published without review) or
     * its submitted delineation was approved by an expert. Resident imagery stays private until then.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('drone_imagery_layers.status', 'ready')
            ->where(fn (Builder $published) => $published
                ->whereHas('user', fn (Builder $user) => $user->where('role', 'expert'))
                ->orWhereHas('delineation', fn (Builder $delineation) => $delineation
                    ->where('is_approved', true)
                    ->where('is_rejected', false)));
    }

    /** The key of self::STAGES this layer is at. Uses the user (role) and delineation relations. */
    public function stage(): string
    {
        return match ($this->status) {
            'failed' => 'failed',
            'awaiting_review' => $this->isSegmenting() ? 'detecting' : 'waiting_owner',
            'finalizing' => 'finalizing',
            'ready' => match ($this->reviewState()) {
                'approved' => 'published',
                'rejected' => 'rejected',
                'pending' => 'pending_expert',
                // Experts' imagery is published without a delineation; a resident's is not.
                default => $this->user?->isExpert() ? 'published' : 'not_submitted',
            },
            default => 'processing',
        };
    }

    /** Since when the owner has had the AI draft (or, without one, the processed imagery) to review. */
    public function waitingForOwnerSince(): ?Carbon
    {
        return $this->status === 'awaiting_review' ? ($this->segmented_at ?? $this->created_at) : null;
    }

    /** Layers at one stage (a key of self::STAGES); the same rules as stage(). */
    public function scopeInStage(Builder $query, string $stage): Builder
    {
        $segmenting = ['queued', 'processing'];

        return match ($stage) {
            'failed' => $query->where('drone_imagery_layers.status', 'failed'),
            'detecting' => $query->where('drone_imagery_layers.status', 'awaiting_review')
                ->whereIn('drone_imagery_layers.segmentation_status', $segmenting),
            'waiting_owner' => $query->where('drone_imagery_layers.status', 'awaiting_review')
                ->where(fn (Builder $q) => $q->whereNull('drone_imagery_layers.segmentation_status')
                    ->orWhereNotIn('drone_imagery_layers.segmentation_status', $segmenting)),
            'finalizing' => $query->where('drone_imagery_layers.status', 'finalizing'),
            'published' => $query->published(),
            'pending_expert' => $query->awaitingExpertReview(),
            'rejected' => $query->where('drone_imagery_layers.status', 'ready')
                ->whereHas('delineation', fn (Builder $d) => $d->where('is_approved', false)->where('is_rejected', true)),
            'not_submitted' => $query->where('drone_imagery_layers.status', 'ready')
                ->whereDoesntHave('delineation')
                ->whereDoesntHave('user', fn (Builder $user) => $user->where('role', 'expert')),
            'processing' => $query->whereNotIn('drone_imagery_layers.status', ['awaiting_review', 'finalizing', 'ready', 'failed']),
            default => $query,
        };
    }

    /** Waiting for the owner for longer than STALE_DRAFT_DAYS. */
    public function scopeStaleOwnerDraft(Builder $query): Builder
    {
        return $query->inStage('waiting_owner')
            ->whereRaw('COALESCE(drone_imagery_layers.segmented_at, drone_imagery_layers.created_at) < ?', [now()->subDays(self::STALE_DRAFT_DAYS)]);
    }

    /** A resident's tiled imagery whose delineation waits for an expert: experts see it to review. */
    public function scopeAwaitingExpertReview(Builder $query): Builder
    {
        return $query->where('drone_imagery_layers.status', 'ready')
            ->whereHas('delineation', fn (Builder $delineation) => $delineation->pending());
    }

    public function isPublished(): bool
    {
        if ($this->status !== 'ready') {
            return false;
        }

        $delineation = $this->delineation;

        return (bool) $this->user?->isExpert()
            || ($delineation !== null && $delineation->is_approved && ! $delineation->is_rejected);
    }

    /** An expert approved the delineation submitted from it: the imagery is view-only from then on. */
    public function isApproved(): bool
    {
        return $this->reviewState() === 'approved';
    }

    /** null (no delineation submitted from this imagery), pending, approved or rejected. */
    public function reviewState(): ?string
    {
        $delineation = $this->delineation;
        if ($delineation === null) {
            return null;
        }

        return match (true) {
            (bool) $delineation->is_approved => 'approved',
            (bool) $delineation->is_rejected => 'rejected',
            default => 'pending',
        };
    }

    /** The AI delineation (SegmentDroneImagery) is queued or running. */
    public function isSegmenting(): bool
    {
        return in_array($this->segmentation_status, ['queued', 'processing'], true);
    }

    /** The genus classification (ClassifyDroneImagery) is queued or running. */
    public function isClassifying(): bool
    {
        return in_array($this->classification_status, ['queued', 'processing'], true);
    }

    /** Either AI job is queued or running (the layer's files are in use). */
    public function isRunningAi(): bool
    {
        return $this->isSegmenting() || $this->isClassifying();
    }

    /**
     * The AI results as one set of stats: the delineation's ($segmentationStats, default the stored ones)
     * plus the genus classification's (genus_suggestion, canopy_count, canopies_by_genus). Copies of the
     * genus results stored with the delineation before the two were split are ignored. Null without a
     * delineation.
     */
    public function aiStats(?array $segmentationStats = null): ?array
    {
        $stats = $segmentationStats ?? $this->segmentation_stats;
        if (! is_array($stats)) {
            return null;
        }

        $stats = array_diff_key($stats, array_flip(self::CLASSIFICATION_STAT_KEYS));
        $classification = is_array($this->classification_stats) ? $this->classification_stats : [];

        return [...$stats, ...array_intersect_key($classification, array_flip(self::CLASSIFICATION_STAT_KEYS))];
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

    /**
     * Per-pixel genus map from the last genus classification (RGB PNG, see ml/predict_genus.py) and its
     * metadata JSON (position, offset, thresholds). Used to recount the genus when the owner edits the areas.
     */
    public function genusLabelsPath(): string
    {
        return "drone-imagery/processed/{$this->id}/genus_labels.png";
    }

    public function genusLabelsMetaPath(): string
    {
        return "drone-imagery/processed/{$this->id}/genus_labels.json";
    }

    /**
     * Every canopy the last genus classification pinpointed (genus, confidence, area, centre and outline),
     * see ml/predict_genus.py --canopies-out. Shown as pins on the map and in the review editor.
     */
    /**
     * The mangrove areas exactly as the AI drew them (SegmentDroneImagery), kept when the owner saves
     * corrections over ai_extent.json, so the AI can be scored against the reviewed boundary
     * (ExtentAccuracyReport, `php artisan mangroves:accuracy`).
     */
    public function aiOriginalExtentPath(): string
    {
        return "drone-imagery/processed/{$this->id}/ai_extent_original.json";
    }

    public function canopiesPath(): string
    {
        return "drone-imagery/processed/{$this->id}/canopies.json";
    }

    public function currentEdits(): array
    {
        return $this->edits ?? self::EMPTY_EDITS;
    }

    /**
     * "photos" when the imagery was made from drone photos (JPG/PNG placed by DronePhotoGeoreferencer), else
     * "geotiff". Set by ProcessDroneImagery; before that, from the uploaded files when a query loaded
     * withExists('photoFiles').
     */
    public function sourceFormat(): string
    {
        if (isset($this->metadata['source_format'])) {
            return $this->metadata['source_format'];
        }

        return ($this->attributes['photo_files_exists'] ?? false) ? 'photos' : 'geotiff';
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

    /** The delineation submitted from this imagery's mangrove areas (one per layer). */
    public function delineation(): HasOne
    {
        return $this->hasOne(Delineation::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(DroneImageryFile::class)->orderBy('sort_order');
    }

    /** Uploaded JPG/PNG photos (for withExists in listings; see sourceFormat()). */
    public function photoFiles(): HasMany
    {
        return $this->hasMany(DroneImageryFile::class)->whereIn('mime_type', ['image/jpeg', 'image/png']);
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
