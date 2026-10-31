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

    /** @return array<string, mixed> */
    public function toMapPayload(): array
    {
        return array_merge($this->only([
            'id', 'user_id', 'delineation_id', 'name', 'features', 'suitability',
            'recommended_genera', 'recommendation', 'site_notes',
        ]), [
            'expert_name' => $this->user?->name,
            'delineation_name' => $this->delineation?->name,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ]);
    }
}
