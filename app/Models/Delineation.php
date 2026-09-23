<?php

namespace App\Models;

use App\Notifications\DelineationSubmittedForReview;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Delineation extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::deleting(function (Delineation $delineation) {
            $delineation->deleteReviewNotifications();
        });
    }

    protected $fillable = [
        'user_id',
        'name',
        'notes',
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

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedBy()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function mapDashboardUrlFor(User $user): string
    {
        $params = ['delineation' => $this->id];

        return $user->isExpert()
            ? route('expert.dashboard', $params)
            : route('dashboard', $params);
    }

    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
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
