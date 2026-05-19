<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Analysis extends Model
{
    protected $table = 'analysis';

    protected $fillable = [
        'user_id',
        'mangrove_data_id',
        'analysis_type',
        'image_url',
        'species_detected',
        'classification_confidence',
        'detected_damage',
        'recommendations',
        'status',
        'results'
    ];

    protected $casts = [
        'classification_confidence' => 'float',
        'results' => 'array'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mangroveData(): BelongsTo
    {
        return $this->belongsTo(MangroveData::class);
    }
}
