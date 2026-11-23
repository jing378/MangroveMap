<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** A reminder an admin sent (see App\Services\AdminReminderSender). */
class AdminReminder extends Model
{
    /** Experts about every pending delineation. */
    public const PENDING_REVIEWS = 'pending_reviews';

    /** Owners of drone surveys whose AI draft has waited over DroneImageryLayer::STALE_DRAFT_DAYS. */
    public const STALE_DRAFTS = 'stale_drafts';

    /** Experts about one pending delineation (target_id). */
    public const DELINEATION = 'delineation';

    /** The owner of one drone survey waiting for them (target_id). */
    public const DRONE_LAYER = 'drone_layer';

    public const KINDS = [self::PENDING_REVIEWS, self::STALE_DRAFTS, self::DELINEATION, self::DRONE_LAYER];

    /** The same reminder (kind + target) goes out at most once in this many hours. */
    public const COOLDOWN_HOURS = 24;

    protected $fillable = ['kind', 'target_id', 'sent_by', 'recipients', 'note'];

    protected $casts = [
        'target_id' => 'integer',
        'recipients' => 'integer',
    ];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    /** The newest reminder of this kind and target still inside the cooldown, or null. */
    public static function recent(string $kind, ?int $targetId = null): ?self
    {
        return self::query()
            ->where('kind', $kind)
            ->when($targetId === null, fn ($query) => $query->whereNull('target_id'), fn ($query) => $query->where('target_id', $targetId))
            ->where('created_at', '>', now()->subHours(self::COOLDOWN_HOURS))
            ->with('sender:id,name')
            ->latest('id')
            ->first();
    }

    /**
     * For a list page: the newest reminder still inside the cooldown per target id.
     *
     * @param  array<int, int>  $targetIds
     * @return Collection<int, self> keyed by target_id
     */
    public static function recentFor(string $kind, array $targetIds): Collection
    {
        if ($targetIds === []) {
            return collect();
        }

        return self::query()
            ->where('kind', $kind)
            ->whereIn('target_id', $targetIds)
            ->where('created_at', '>', now()->subHours(self::COOLDOWN_HOURS))
            ->with('sender:id,name')
            ->orderBy('id')
            ->get()
            ->keyBy('target_id');
    }

    /** When this reminder may be sent again. */
    public function availableAgainAt(): Carbon
    {
        return $this->created_at->copy()->addHours(self::COOLDOWN_HOURS);
    }
}
