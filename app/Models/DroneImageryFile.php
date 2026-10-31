<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DroneImageryFile extends Model
{
    protected $fillable = [
        'drone_imagery_layer_id',
        'original_name',
        'stored_path',
        'mime_type',
        'file_size',
        'sort_order',
    ];

    public function layer(): BelongsTo
    {
        return $this->belongsTo(DroneImageryLayer::class, 'drone_imagery_layer_id');
    }
}
