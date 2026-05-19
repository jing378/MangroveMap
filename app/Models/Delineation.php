<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Delineation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'notes',
        'features',
        'is_approved',
        'approved_at',
        'approved_by',
        'is_rejected',
        'rejected_at',
        'rejected_by',
        'rejection_notes',
    ];

    protected $casts = [
        'features' => 'array',
        'is_approved' => 'boolean',
        'approved_at' => 'datetime',
        'is_rejected' => 'boolean',
        'rejected_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejectedBy()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }

    public function scopeRejected($query)
    {
        return $query->where('is_rejected', true);
    }

    public function scopePending($query)
    {
        return $query->where('is_approved', false)->where('is_rejected', false);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
}
