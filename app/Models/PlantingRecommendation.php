<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlantingRecommendation extends Model
{
    public const SUITABILITY_LEVELS = [
        'highly_suitable' => 'Highly suitable',
        'moderately_suitable' => 'Moderately suitable',
        'marginally_suitable' => 'Marginally suitable',
        'not_suitable' => 'Not suitable',
    ];

    public const RECOMMENDED_GENERA = ['Rhizophora', 'Avicennia', 'Sonneratia'];

    protected $fillable = [
        'user_id',
        'delineation_id',
        'name',
        'features',
        'suitability',
        'recommended_genera',
        'recommendation',
        'site_notes',
    ];

    protected $casts = [
        'features' => 'array',
        'recommended_genera' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function delineation(): BelongsTo
    {
        return $this->belongsTo(Delineation::class);
    }

    /**
     * Experts see every recommendation; residents see freely drawn areas and those
     * on delineations that are still approved (same rule as the details panel).
     */
    public function scopeVisibleOnMapTo($query, User $user)
    {
        if ($user->isExpert()) {
            return $query;
        }

        return $query->where(fn($q) => $q
            ->whereNull('delineation_id')
            ->orWhereHas('delineation', fn($d) => $d->approved()));
    }

    /** @return array<string, mixed> */
    public function toMapPayload(): array
    {
        return array_merge($this->only([
            'id', 'user_id', 'delineation_id', 'name', 'features', 'suitability',
            'recommended_genera', 'recommendation', 'site_notes',
        ]), [
            'suitability_label' => self::SUITABILITY_LEVELS[$this->suitability] ?? $this->suitability,
            'expert_name' => $this->user?->name,
            'delineation_name' => $this->delineation?->name,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ]);
    }

    /** Summary shown in the Map/Delineate details panel of the linked delineation. */
    public function toDelineationPanelPayload(): array
    {
        return [
            'id' => $this->id,
            'suitability' => $this->suitability,
            'suitability_label' => self::SUITABILITY_LEVELS[$this->suitability] ?? $this->suitability,
            'recommended_genera' => $this->recommended_genera ?? [],
            'recommendation' => $this->recommendation,
            'expert_name' => $this->user?->name,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
