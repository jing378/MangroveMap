<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DelineationAnnualObservationBoundaryRevision extends Model
{
    protected $fillable = [
        'annual_observation_id',
        'version',
        'features',
        'created_by',
    ];

    protected $casts = [
        'version' => 'integer',
        'features' => 'array',
    ];

    public function annualObservation(): BelongsTo
    {
        return $this->belongsTo(DelineationAnnualObservation::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}