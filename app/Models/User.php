<?php

namespace App\Models;

use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, MustVerifyEmailTrait, Notifiable;

    protected $fillable = [
        'name',
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'email',
        'password',
        'role',
        'organization',
        'phone',
        'profile_image',
        'last_login_at',
        'expert_application_status',
        'expert_qualifications',
        'expert_certificate_path',
        'expert_certificate_name',
        'expert_applied_at',
        'expert_reviewed_at',
        'expert_reviewed_by',
        'expert_rejection_notes',
    ];

    public const ROLE_ADMIN = 'admin';

    public const ROLE_EXPERT = 'expert';

    public const ROLE_RESIDENT = 'end_user';

    /** Display label for each stored role value; residents are stored as end_user. */
    public const ROLE_LABELS = [
        self::ROLE_RESIDENT => 'Resident',
        self::ROLE_EXPERT => 'Expert',
        self::ROLE_ADMIN => 'Administrator',
    ];

    public const EXPERT_APPLICATION_PENDING = 'pending';

    public const EXPERT_APPLICATION_APPROVED = 'approved';

    public const EXPERT_APPLICATION_REJECTED = 'rejected';

    /** Private local disk folder for expert application certificates. */
    public const EXPERT_CERTIFICATE_DIRECTORY = 'expert-certificates';

    /** Accepted certificate types and the size limit (in KB) shared by the form and the server. */
    public const EXPERT_CERTIFICATE_MIMES = ['pdf', 'jpg', 'jpeg', 'png'];

    public const EXPERT_CERTIFICATE_MAX_KB = 5120;

    protected $hidden = [
        'password',
        'remember_token',
        'expert_certificate_path',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'expert_applied_at' => 'datetime',
        'expert_reviewed_at' => 'datetime',
        'password' => 'hashed',
    ];

    protected static function booted(): void
    {
        // Anyone created without a valid role (sign-ups, factories, imports) is a resident.
        // Experts are promoted by an admin, and admin accounts are only ever set explicitly.
        static::creating(function (User $user) {
            if (! array_key_exists((string) $user->role, self::ROLE_LABELS)) {
                $user->role = self::ROLE_RESIDENT;
            }
        });

        static::deleted(function (User $user) {
            $user->deleteExpertApplicationNotifications();

            if ($user->expert_certificate_path) {
                \Illuminate\Support\Facades\Storage::disk('local')->delete($user->expert_certificate_path);
            }
        });

        // name stays the full display name used across the app ("Juan D. Dela Cruz Jr.");
        // first/middle/last/suffix are the editable parts.
        static::saving(function (User $user) {
            if ($user->isDirty(['first_name', 'middle_name', 'last_name', 'suffix'])) {
                $user->first_name = trim((string) $user->first_name);
                $user->middle_name = trim((string) $user->middle_name) ?: null;
                $user->last_name = trim((string) $user->last_name);
                $user->suffix = self::normalizeSuffix($user->suffix);
                $user->name = self::displayName($user->first_name, $user->middle_name, $user->last_name, $user->suffix);
            } elseif ($user->isDirty('name')) {
                $parts = self::parseName((string) $user->name);
                $user->first_name = $parts['first_name'];
                $user->middle_name = $parts['middle_name'];
                $user->last_name = $parts['last_name'];
                $user->suffix = $parts['suffix'];
            }
        });
    }

    /** Generational suffixes, keyed by their lowercase form without dots. */
    private const NAME_SUFFIXES = ['jr' => 'Jr.', 'sr' => 'Sr.', 'ii' => 'II', 'iii' => 'III', 'iv' => 'IV', 'v' => 'V', 'vi' => 'VI'];

    /** "jr" => "Jr.", "iii" => "III"; anything else is kept as typed. Empty becomes null. */
    public static function normalizeSuffix(?string $suffix): ?string
    {
        $suffix = trim((string) $suffix);

        if ($suffix === '') {
            return null;
        }

        return self::NAME_SUFFIXES[strtolower(rtrim($suffix, '.'))] ?? $suffix;
    }

    /** "Dela Paz" => "D." */
    public static function middleInitial(?string $middleName): ?string
    {
        $middleName = trim((string) $middleName);

        return $middleName === '' ? null : mb_strtoupper(mb_substr($middleName, 0, 1)) . '.';
    }

    /** Full name as shown in the app: first name, middle initial, last name, suffix. */
    public static function displayName(?string $first, ?string $middle, ?string $last, ?string $suffix): string
    {
        $parts = [trim((string) $first), self::middleInitial($middle), trim((string) $last), self::normalizeSuffix($suffix)];

        return implode(' ', array_filter($parts, fn ($part) => $part !== null && $part !== ''));
    }

    /**
     * Best-effort split of a full name typed as one string (old accounts, admin edits).
     * "Juan D. Dela Cruz Jr." => first Juan, middle D, last Dela Cruz, suffix Jr.
     *
     * @return array{first_name: string, middle_name: ?string, last_name: string, suffix: ?string}
     */
    public static function parseName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY);

        $suffix = null;
        if (count($parts) >= 3 && isset(self::NAME_SUFFIXES[strtolower(rtrim(end($parts), '.'))])) {
            $suffix = self::normalizeSuffix(array_pop($parts));
        }

        [$first, $last] = self::splitName(implode(' ', $parts));

        // A lone initial right before the surname is the middle initial.
        $middle = null;
        $firstParts = preg_split('/\s+/', $first, -1, PREG_SPLIT_NO_EMPTY);
        if (count($firstParts) >= 2 && preg_match('/^(\p{L})\.?$/u', end($firstParts), $match)) {
            array_pop($firstParts);
            $middle = mb_strtoupper($match[1]);
            $first = implode(' ', $firstParts);
        }

        return ['first_name' => $first, 'middle_name' => $middle, 'last_name' => $last, 'suffix' => $suffix];
    }

    /** Lowercase words that begin a compound surname, e.g. "Dela Cruz", "De los Santos". */
    private const SURNAME_PARTICLES = ['de', 'del', 'dela', 'delos', 'della', 'di', 'da', 'des', 'du', 'la', 'las', 'los', 'san', 'santa', 'sta', 'sto', 'van', 'von', 'y'];

    /**
     * Split a full name into [first, last]. The last word is the surname, together with any
     * particles in front of it ("Juan Dela Cruz" => ["Juan", "Dela Cruz"]).
     *
     * @return array{0: string, 1: string}
     */
    public static function splitName(string $name): array
    {
        $parts = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY);

        if (count($parts) <= 1) {
            return [$parts[0] ?? '', ''];
        }

        $lastStart = count($parts) - 1;
        // A dotted single letter ("Y.") is a middle initial, not the particle "y".
        while ($lastStart > 1
            && ! preg_match('/^\p{L}\.$/u', $parts[$lastStart - 1])
            && in_array(strtolower(rtrim($parts[$lastStart - 1], '.')), self::SURNAME_PARTICLES, true)) {
            $lastStart--;
        }

        return [
            implode(' ', array_slice($parts, 0, $lastStart)),
            implode(' ', array_slice($parts, $lastStart)),
        ];
    }

    public static function roleLabelFor(?string $role): string
    {
        return self::ROLE_LABELS[$role] ?? self::ROLE_LABELS[self::ROLE_RESIDENT];
    }

    public function roleLabel(): string
    {
        return self::roleLabelFor($this->role);
    }

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

    /** A resident whose expert application was rejected can send a new one from their profile. */
    public function canReapplyAsExpert(): bool
    {
        return $this->role === self::ROLE_RESIDENT
            && $this->expert_application_status === self::EXPERT_APPLICATION_REJECTED;
    }

    /** Tells every admin that this user's expert application (new or resubmitted) is waiting for review. */
    public function notifyAdminsOfExpertApplication(bool $resubmitted = false): void
    {
        try {
            \Illuminate\Support\Facades\Notification::send(
                self::where('role', 'admin')->get(),
                new \App\Notifications\AdminAlert(
                    $resubmitted ? 'Expert application resubmitted' : 'New expert account application',
                    $resubmitted
                        ? 'A previously rejected applicant sent a new expert account application for your review.'
                        : 'A new expert account application is waiting for your review.',
                    'expert_application',
                    // Relative, so the link keeps working if the site's address changes.
                    route('admin.users', [], false) . '#expert-applications',
                    'Review',
                    details: [
                        // Lets the notification be removed once the application is reviewed.
                        'applicant_id' => $this->id,
                        'applicant_name' => $this->name,
                        'organization' => $this->organization,
                    ],
                ),
            );
        } catch (\Throwable $exception) {
            // The application is saved either way; admins still see it on the Users page.
            logger()->error('Failed to notify admins of expert application', [
                'user_id' => $this->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Remove the admins' "New expert account application" notifications for this applicant, once the
     * application is reviewed or the account deleted (nothing is left to review). Older notifications
     * have no applicant_id, so those are matched by the applicant's name.
     */
    public function deleteExpertApplicationNotifications(): void
    {
        \Illuminate\Support\Facades\DB::table('notifications')
            ->where('type', \App\Notifications\AdminAlert::class)
            ->where('data->type', 'expert_application')
            ->where(function ($query) {
                $query->where('data->applicant_id', $this->id)
                    ->orWhere(function ($legacy) {
                        $legacy->whereNull('data->applicant_id')
                            ->where('data->applicant_name', $this->getOriginal('name') ?: $this->name);
                    });
            })
            ->delete();
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
        return $this->role === self::ROLE_ADMIN;
    }

    public function isEndUser(): bool
    {
        return $this->role === self::ROLE_RESIDENT;
    }

    public function isExpert(): bool
    {
        return $this->role === self::ROLE_EXPERT;
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
