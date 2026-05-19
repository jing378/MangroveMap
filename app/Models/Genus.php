<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Genus extends Model
{
    protected $table = 'genera';

    protected $fillable = [
        'common_name',
        'scientific_name',
        'genus',
        'family',
        'description',
        'conservation_status',
        'geographical_distribution',
        'salinity_tolerance'
    ];

    public function mangroveData(): HasMany
    {
        return $this->hasMany(MangroveData::class);
    }
}
