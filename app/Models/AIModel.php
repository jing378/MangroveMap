<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AIModel extends Model
{
    protected $table = 'ai_models';

    protected $fillable = [
        'name',
        'model_type',
        'version',
        'accuracy',
        'training_date',
        'dataset_size',
        'status',
        'description',
        'model_path',
        'is_active'
    ];

    protected $casts = [
        'accuracy' => 'float',
        'training_date' => 'datetime',
        'is_active' => 'boolean'
    ];

    public function analyses(): HasMany
    {
        return $this->hasMany(Analysis::class);
    }
}