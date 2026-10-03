<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DelineationAnnualObservation extends Model
{
    protected $fillable = [
        'delineation_id',
        'created_by',
        'year',
        'observation_date',
        'coverage_area_ha',
        'genus_distribution',
        'source',
    ];

    protected $casts = [
        'observation_date' => 'date',
        'coverage_area_ha' => 'float',
        'genus_distribution' => 'array',
    ];

    public function delineation(): BelongsTo
    {
        return $this->belongsTo(Delineation::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
