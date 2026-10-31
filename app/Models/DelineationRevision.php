<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DelineationRevision extends Model
{
    protected $fillable = [
        'delineation_id',
        'revised_by',
        'version',
        'snapshot',
    ];

    protected $casts = [
        'snapshot' => 'array',
    ];

    public function delineation(): BelongsTo
    {
        return $this->belongsTo(Delineation::class);
    }

    public function reviser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revised_by');
    }
}