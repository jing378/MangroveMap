<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DelineationAnnualObservation extends Model
{
    /** Where an expert saw the change they record while editing a boundary. */
    public const SOURCES = [
        'field_survey' => 'Field survey',
        'drone_imagery' => 'Drone imagery',
        'satellite_imagery' => 'Satellite imagery',
    ];

    /** Observation dates are local field dates in Southern Leyte, while the app clock runs on UTC. */
    public const LOCAL_TIMEZONE = 'Asia/Manila';

    /** Source of the baseline year created from the boundary as it was first drawn. */
    public const BASELINE_SOURCE = 'Original delineation';

    protected $fillable = [
        'delineation_id',
        'created_by',
        'year',
        'observation_date',
        'coverage_area_ha',
        'genus_distribution',
        'genera_present',
        'source',
    ];

    protected $casts = [
        'observation_date' => 'date',
        'coverage_area_ha' => 'float',
        'genus_distribution' => 'array',
        'genera_present' => 'array',
    ];

    public function delineation(): BelongsTo
    {
        return $this->belongsTo(Delineation::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function boundaryRevisions(): HasMany
    {
        return $this->hasMany(DelineationAnnualObservationBoundaryRevision::class, 'annual_observation_id')
            ->orderBy('version');
    }
}
