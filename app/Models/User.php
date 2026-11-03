<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable, MustVerifyEmailTrait;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'organization',
        'phone',
        'profile_image',
        'last_login_at',
        'expert_application_status',
        'expert_qualifications',
        'expert_applied_at',
        'expert_reviewed_at',
        'expert_reviewed_by',
        'expert_rejection_notes',
    ];

    public const EXPERT_APPLICATION_PENDING = 'pending';
    public const EXPERT_APPLICATION_APPROVED = 'approved';
    public const EXPERT_APPLICATION_REJECTED = 'rejected';

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'expert_applied_at' => 'datetime',
        'expert_reviewed_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function expertReviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'expert_reviewed_by');
    }

    public function scopePendingExpertApplications($query)
    {
        return $query->where('expert_application_status', self::EXPERT_APPLICATION_PENDING);
    }

    public function hasPendingExpertApplication(): bool
    {
        return $this->expert_application_status === self::EXPERT_APPLICATION_PENDING;
    }

    public function activities(): HasMany
    {
        return $this->hasMany(UserActivity::class);
    }

    public function analyses(): HasMany
    {
        return $this->hasMany(Analysis::class);
    }

    public function delineations(): HasMany
    {
        return $this->hasMany(Delineation::class);
    }

    public function plantingRecommendations(): HasMany
    {
        return $this->hasMany(PlantingRecommendation::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isEndUser(): bool
    {
        return $this->role === 'end_user';
    }

    public function isExpert(): bool
    {
        return $this->role === 'expert';
    }

    public function isResident(): bool
    {
        return $this->isEndUser();
    }

    public function homeRoute(): string
    {
        return match ($this->role) {
            'admin' => route('admin.dashboard'),
            'expert' => route('expert.dashboard'),
            default => route('dashboard'),
        };
    }
    public function hasVerifiedEmail(): bool
    {
        return $this->email_verified_at !== null;
    }
}
