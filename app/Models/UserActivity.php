<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class UserActivity extends Model
{
    protected $fillable = [
        'user_id',
        'activity',
        'module',
        'status',
        'subject_type',
        'subject_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public static function record(
        int|User $user,
        string $activity,
        string $module,
        string $status = 'success',
        ?Model $subject = null,
        ?\DateTimeInterface $createdAt = null,
    ): self {
        $userId = $user instanceof User ? $user->id : $user;

        $activity = static::create([
            'user_id' => $userId,
            'activity' => $activity,
            'module' => $module,
            'status' => $status,
            'subject_type' => $subject ? $subject->getMorphClass() : null,
            'subject_id' => $subject?->getKey(),
        ]);

        if ($createdAt) {
            $activity->created_at = $createdAt;
            $activity->updated_at = $createdAt;
            $activity->saveQuietly();
        }

        return $activity;
    }

    public static function statusForAnalysis(string $status): string
    {
        return match ($status) {
            'completed' => 'success',
            'pending', 'processing' => 'pending',
            'failed' => 'rejected',
            default => 'secondary',
        };
    }

    public static function labelForAnalysis(Analysis $analysis): string
    {
        $type = ucwords(str_replace('_', ' ', $analysis->analysis_type));

        return match ($analysis->analysis_type) {
            'classification' => $analysis->species_detected
                ? "Classification — {$analysis->species_detected}"
                : 'Ran genus classification',
            'change_detection' => 'Ran change detection',
            'damage_assessment' => 'Ran damage assessment',
            default => "Ran {$type}",
        };
    }

    public static function recordAnalysis(Analysis $analysis, ?\DateTimeInterface $createdAt = null): self
    {
        return static::record(
            $analysis->user_id,
            static::labelForAnalysis($analysis),
            'Classification',
            static::statusForAnalysis($analysis->status),
            $analysis,
            $createdAt ?? $analysis->created_at,
        );
    }

    public static function recordDelineationSubmitted(Delineation $delineation): self
    {
        return static::record(
            $delineation->user_id,
            'Submitted delineation: ' . $delineation->name,
            'Map Delineation',
            'pending',
            $delineation,
            $delineation->created_at,
        );
    }

    public static function recordDelineationApproved(Delineation $delineation, User $expert): self
    {
        return static::record(
            $expert,
            'Approved delineation: ' . $delineation->name,
            'Expert Review',
            'success',
            $delineation,
            $delineation->approved_at ?? now(),
        );
    }

    public static function recordDelineationRejected(Delineation $delineation, User $expert): self
    {
        return static::record(
            $expert,
            'Rejected delineation: ' . $delineation->name,
            'Expert Review',
            'rejected',
            $delineation,
            $delineation->rejected_at ?? now(),
        );
    }

    public static function recordDelineationPublished(Delineation $delineation, User $expert): self
    {
        return static::record(
            $expert,
            'Published delineation: ' . $delineation->name,
            'Map Delineation',
            'success',
            $delineation,
            $delineation->created_at,
        );
    }
}
