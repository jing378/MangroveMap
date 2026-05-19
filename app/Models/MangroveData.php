<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MangroveData extends Model
{
    protected $table = 'mangrove_data';

    protected $fillable = [
        'region',
        'latitude',
        'longitude',
        'coverage_area_km2',
        'genus_id',
        'health_status',
        'degradation_level',
        'satellite_image_url',
        'observation_date',
        'data_source',
        'confidence_score'
    ];

    protected $casts = [
        'coverage_area_km2' => 'float',
        'confidence_score' => 'float',
        'observation_date' => 'datetime'
    ];

    // Relationship to Genus
    public function genus(): BelongsTo
    {
        return $this->belongsTo(Genus::class);
    }

    // Relationship to Analysis
    public function analyses(): HasMany
    {
        return $this->hasMany(Analysis::class);
    }
}
