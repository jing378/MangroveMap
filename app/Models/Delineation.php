<?php

namespace App\Models;

use App\Notifications\DelineationSubmittedForReview;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class Delineation extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::deleting(function (Delineation $delineation) {
            $delineation->deleteReviewNotifications();
            // Re-surveys of a deleted area stand as areas of their own (the foreign key does the same on MySQL).
            static::query()
                ->where('reassesses_delineation_id', $delineation->id)
                ->update(['reassesses_delineation_id' => null]);
        });

        // A delineation from drone imagery shares its name with the imagery (listed on Drone Upload).
        static::saved(function (Delineation $delineation) {
            $name = trim((string) $delineation->name);
            if ($delineation->drone_imagery_layer_id === null || $name === '' || ! $delineation->wasChanged('name')) {
                return;
            }
            DroneImageryLayer::query()
                ->whereKey($delineation->drone_imagery_layer_id)
                ->where('name', '!=', $name)
                ->update(['name' => $name]);
        });
    }

    /** Where a delineation came from (the `source` column), with the label the admin pages show. */
    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_DRONE = 'drone';

    public const SOURCES = [
        self::SOURCE_MANUAL => 'Hand-drawn',
        self::SOURCE_DRONE => 'Drone survey (AI-assisted)',
    ];

    protected $attributes = [
        'source' => self::SOURCE_MANUAL,
    ];

    protected $fillable = [
        'user_id',
        'drone_imagery_layer_id',
        'source',
        'reassesses_delineation_id',
        'name',
        'notes',
        'planting_recommendation',
        'features',
        'is_approved',
        'approved_at',
        'approved_by',
        'is_rejected',
        'rejected_at',
        'rejected_by',
        'rejection_notes',
    ];

    protected $casts = [
        'features' => 'array',
        'is_approved' => 'boolean',
        'approved_at' => 'datetime',
        'is_rejected' => 'boolean',
        'rejected_at' => 'datetime',
    ];

    /** Days a rejected delineation is kept for the resident to revise before experts may remove it. */
    public const REJECTED_REMOVAL_GRACE_DAYS = 7;

    /** When experts may remove this rejected delineation (null when it is not rejected). */
    public function rejectedRemovableAt(): ?Carbon
    {
        if (! $this->is_rejected || $this->is_approved) {
            return null;
        }

        // Older rejections may predate rejected_at; fall back to the last update.
        $rejectedAt = $this->rejected_at ?? $this->updated_at ?? now();

        return $rejectedAt->copy()->addDays(self::REJECTED_REMOVAL_GRACE_DAYS);
    }

    /** Made from a drone survey's AI-drawn areas (even when that imagery was deleted since). */
    public function isFromDrone(): bool
    {
        return $this->source === self::SOURCE_DRONE || $this->drone_imagery_layer_id !== null;
    }

    public function sourceLabel(): string
    {
        return self::SOURCES[$this->isFromDrone() ? self::SOURCE_DRONE : self::SOURCE_MANUAL];
    }

    public function canBeRemovedByExpert(): bool
    {
        $removableAt = $this->rejectedRemovableAt();

        return $removableAt !== null && ! $removableAt->isFuture();
    }

    /** Snapshot stored in delineation_revisions before an edit. */
    public function recordRevision(User $editor, string $type): DelineationRevision
    {
        return $this->revisions()->create([
            'revised_by' => $editor->id,
            'version' => ((int) $this->revisions()->max('version')) + 1,
            'snapshot' => [
                'type' => $type,
                'name' => $this->name,
                'notes' => $this->notes,
                'features' => $this->features,
                'is_approved' => $this->is_approved,
                'is_rejected' => $this->is_rejected,
            ],
        ]);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function droneImageryLayer()
    {
        return $this->belongsTo(DroneImageryLayer::class);
    }

    /** The already mapped area this drone re-survey reassesses (null for a new area). */
    public function reassessedDelineation()
    {
        return $this->belongsTo(Delineation::class, 'reassesses_delineation_id');
    }

    /** Drone re-surveys submitted for this area. */
    public function reassessments(): HasMany
    {
        return $this->hasMany(Delineation::class, 'reassesses_delineation_id');
    }

    /**
     * An approved re-survey: its boundary was copied onto the area it reassesses, so it is not an area of its own.
     * Pending and rejected ones are still shown (to their owner and the experts reviewing them).
     */
    public function isAppliedReassessment(): bool
    {
        return $this->reassesses_delineation_id !== null && $this->is_approved && ! $this->is_rejected;
    }

    /** Without applied re-surveys (see isAppliedReassessment): for the map and the hectare totals. */
    public function scopeWithoutAppliedReassessments($query)
    {
        return $query->where(fn ($q) => $q
            ->whereNull('delineations.reassesses_delineation_id')
            ->orWhere('delineations.is_approved', false)
            ->orWhere('delineations.is_rejected', true));
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function annualObservations(): HasMany
    {
        return $this->hasMany(DelineationAnnualObservation::class);
    }

    public function plantingRecommendations(): HasMany
    {
        return $this->hasMany(PlantingRecommendation::class)->latest();
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(DelineationRevision::class);
    }

    /** Most recent revision (holds who last changed the boundary/details). */
    public function latestRevision(): HasOne
    {
        return $this->hasOne(DelineationRevision::class)->latestOfMany('version');
    }

    public function rejectedBy()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /**
     * The Delineate page with this record opened: where "View delineation" in notifications goes
     * (bell, pop-ups, phone pushes and emails).
     */
    public function delineateUrlFor(User $user, bool $absolute = true): string
    {
        return self::delineateUrlForId($this->id, $user, $absolute);
    }

    public static function delineateUrlForId(int|string $id, object $user, bool $absolute = true): string
    {
        $isExpert = method_exists($user, 'isExpert') && $user->isExpert();

        return route($isExpert ? 'expert.delineate' : 'delineate', ['delineation' => $id], $absolute);
    }

    /** Approved by an expert: published on the map. */
    public function scopeApproved($query)
    {
        return $query->where('delineations.is_approved', true);
    }

    public function scopeRejected($query)
    {
        return $query->where('is_rejected', true);
    }

    public function scopePending($query)
    {
        return $query->where('is_approved', false)->where('is_rejected', false);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeFromEndUsers($query)
    {
        return $query->whereHas('user', function ($q) {
            $q->where('role', 'end_user');
        });
    }

    /** Max delineation rows loaded on the map dashboard (avoids sorting huge JSON rows in MySQL). */
    public const MAP_QUERY_LIMIT = 150;

    public const MAP_QUERY_LIMIT_OWN = 80;

    /**
     * Sort/limit on ids only, then hydrate full rows (keeps ORDER BY off large JSON columns).
     *
     * @param  Builder<Delineation>  $query
     * @return Collection<int, Delineation>
     */
    public static function fetchForMap($query, int $limit, array $with = []): Collection
    {
        $ids = (clone $query)
            ->select('delineations.id')
            ->reorder()
            ->orderByDesc('delineations.created_at')
            ->limit($limit)
            ->pluck('delineations.id');

        if ($ids->isEmpty()) {
            return new Collection;
        }

        // Keep the eager loads given on $query (e.g. user, approvedBy): the rows are hydrated by a new
        // query, and without them each map payload looked its owner and approver up one by one.
        return static::query()
            ->setEagerLoads($query->getEagerLoads())
            ->with($with)
            ->whereIn('id', $ids)
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Pending resident submissions first, then newest (experts reviewing the queue).
     *
     * @return Collection<int, Delineation>
     */
    public static function fetchResidentDelineationsForExpertMap(int $limit = self::MAP_QUERY_LIMIT): Collection
    {
        $ids = static::query()
            ->fromEndUsers()
            ->withoutAppliedReassessments()
            ->select('delineations.id')
            ->orderByRaw('(delineations.is_approved = 0 AND delineations.is_rejected = 0) DESC')
            ->orderByDesc('delineations.created_at')
            ->limit($limit)
            ->pluck('delineations.id');

        if ($ids->isEmpty()) {
            return new Collection;
        }

        return static::query()
            ->with('user:id,name,role')
            ->whereIn('id', $ids)
            ->orderByRaw('(is_approved = 0 AND is_rejected = 0) DESC')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Remove expert "submitted for review" notifications for this delineation.
     */
    public function deleteReviewNotifications(): void
    {
        DB::table('notifications')
            ->where('type', DelineationSubmittedForReview::class)
            ->where('data->delineation_id', $this->id)
            ->delete();
    }
}
