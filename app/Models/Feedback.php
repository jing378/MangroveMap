<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Feedback extends Model
{
    protected $table = 'feedback';

    /** Screenshots are private: admins view them through an authorized route only. */
    public const SCREENSHOT_DISK = 'local';

    public const TYPES = [
        'bug' => 'Bug / Problem',
        'suggestion' => 'Suggestion',
        'question' => 'Question',
        'other' => 'Other',
    ];

    public const STATUSES = [
        'new' => 'New',
        'reviewed' => 'Reviewed',
        'resolved' => 'Resolved',
    ];

    protected $fillable = [
        'user_id',
        'type',
        'message',
        'page_url',
        'screenshot_path',
        'status',
    ];

    protected static function booted(): void
    {
        static::deleting(function (Feedback $feedback) {
            if ($feedback->screenshot_path) {
                Storage::disk(self::SCREENSHOT_DISK)->delete($feedback->screenshot_path);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ucfirst((string) $this->type);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    /** Badge class from the shared admin table styles. */
    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'new' => 'pending',
            'resolved' => 'success',
            default => '',
        };
    }
}
