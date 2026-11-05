<?php

namespace App\Models;

use App\Notifications\DelineationSubmittedForReview;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;

class Delineation extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::deleting(function (Delineation $delineation) {
            $delineation->deleteReviewNotifications();
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

    protected $fillable = [
        'user_id',
        'drone_imagery_layer_id',
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
        'hidden_at' => 'datetime',
    ];

    /** Days a rejected delineation is kept for the resident to revise before experts may remove it. */
    public const REJECTED_REMOVAL_GRACE_DAYS = 7;

    /** When experts may remove this rejected delineation (null when it is not rejected). */
    public function rejectedRemovableAt(): ?\Illuminate\Support\Carbon
    {
        if (! $this->is_rejected || $this->is_approved) {
            return null;
        }

        // Older rejections may predate rejected_at; fall back to the last update.
        $rejectedAt = $this->rejected_at ?? $this->updated_at ?? now();

        return $rejectedAt->copy()->addDays(self::REJECTED_REMOVAL_GRACE_DAYS);
    }

    public function canBeRemovedByExpert(): bool
    {
        $removableAt = $this->rejectedRemovableAt();

        return $removableAt !== null && ! $removableAt->isFuture();
    }

    public function hiddenBy()
    {
        return $this->belongsTo(User::class, 'hidden_by');
    }

    public function isHiddenFromMap(): bool
    {
        return $this->hidden_at !== null;
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

    public function mapDashboardUrlFor(User $user): string
    {
        $params = ['delineation' => $this->id];

        return $user->isExpert()
            ? route('expert.map', $params)
            : route('map', $params);
    }

    /** Approved and published: delineations an admin hid from the map are excluded. */
    public function scopeApproved($query)
    {
        return $query->where('delineations.is_approved', true)->whereNull('delineations.hidden_at');
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
     * @param  \Illuminate\Database\Eloquent\Builder<Delineation>  $query
     * @return \Illuminate\Database\Eloquent\Collection<int, Delineation>
     */
    public static function fetchForMap($query, int $limit, array $with = []): \Illuminate\Database\Eloquent\Collection
    {
        $ids = (clone $query)
            ->select('delineations.id')
            ->reorder()
            ->orderByDesc('delineations.created_at')
            ->limit($limit)
            ->pluck('delineations.id');

        if ($ids->isEmpty()) {
            return new \Illuminate\Database\Eloquent\Collection;
        }

        return static::query()
            ->with($with)
            ->whereIn('id', $ids)
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Pending resident submissions first, then newest (experts reviewing the queue).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Delineation>
     */
    public static function fetchResidentDelineationsForExpertMap(int $limit = self::MAP_QUERY_LIMIT): \Illuminate\Database\Eloquent\Collection
    {
        $ids = static::query()
            ->fromEndUsers()
            ->select('delineations.id')
            ->orderByRaw('(delineations.is_approved = 0 AND delineations.is_rejected = 0) DESC')
            ->orderByDesc('delineations.created_at')
            ->limit($limit)
            ->pluck('delineations.id');

        if ($ids->isEmpty()) {
            return new \Illuminate\Database\Eloquent\Collection;
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
