<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DroneImageryLayer extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'status',
        'source_type',
        'source_crs',
        'bounds',
        'metadata',
        'mosaic_path',
        'tiles_path',
        'zoom_min',
        'zoom_max',
        'error_message',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'bounds' => 'array',
            'metadata' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(DroneImageryFile::class)->orderBy('sort_order');
    }
}
