<?php

namespace App\Http\Controllers;

use App\Models\Analysis;
use App\Models\Delineation;
use App\Models\DroneImageryLayer;
use App\Models\User;
use App\Models\UserActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        abort_if(Auth::user()->role !== 'admin', 403);

        // Bad filter values are dropped instead of failing the page.
        $filters = Validator::make($request->query(), [
            'search' => ['nullable', 'string', 'max:255'],
            'module' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:' . implode(',', UserActivity::STATUSES)],
            'role' => ['nullable', 'in:admin,end_user,expert'],
            'user' => ['nullable', 'integer'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ])->valid();

        $activities = $this->filteredQuery($filters)
            ->with('user:id,name,email,role')
            ->latest()
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $modules = UserActivity::query()->distinct()->orderBy('module')->pluck('module');

        return view('admin.activity-logs', [
            'activities' => $activities,
            'filters' => $filters,
            'modules' => $modules,
            'filteredUser' => isset($filters['user']) ? User::find($filters['user']) : null,
            'totalToday' => UserActivity::where('created_at', '>=', today())->count(),
            'totalWeek' => UserActivity::where('created_at', '>=', now()->subWeek())->count(),
            'activeUsersWeek' => UserActivity::where('created_at', '>=', now()->subWeek())->distinct()->count('user_id'),
            'failedWeek' => UserActivity::where('created_at', '>=', now()->subWeek())->where('status', 'rejected')->count(),
        ]);
    }

    public function show(UserActivity $activity)
    {
        abort_if(Auth::user()->role !== 'admin', 403);

        $activity->load(['user', 'subject']);

        $relatedActivities = UserActivity::query()
            ->where('user_id', $activity->user_id)
            ->whereKeyNot($activity->id)
            ->latest()
            ->take(10)
            ->get();

        return view('admin.activity-log-show', [
            'activity' => $activity,
            'subjectDetails' => $this->subjectDetails($activity),
            'relatedActivities' => $relatedActivities,
        ]);
    }

    private function filteredQuery(array $filters): Builder
    {
        return UserActivity::query()
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $like = '%' . addcslashes($search, '%_\\') . '%';
                $query->where(function (Builder $query) use ($like) {
                    $query->where('activity', 'like', $like)
                        ->orWhereHas('user', fn(Builder $user) => $user
                            ->where('name', 'like', $like)
                            ->orWhere('email', 'like', $like));
                });
            })
            ->when($filters['module'] ?? null, fn(Builder $query, string $module) => $query->where('module', $module))
            ->when($filters['status'] ?? null, fn(Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['role'] ?? null, fn(Builder $query, string $role) => $query->whereHas('user', fn(Builder $user) => $user->where('role', $role)))
            ->when($filters['user'] ?? null, fn(Builder $query, int|string $userId) => $query->where('user_id', $userId))
            ->when($filters['from'] ?? null, fn(Builder $query, string $from) => $query->where('created_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when($filters['to'] ?? null, fn(Builder $query, string $to) => $query->where('created_at', '<=', Carbon::parse($to)->endOfDay()));
    }

    /**
     * Label/value rows describing the record an activity points at, or null when it has none.
     *
     * @return array{type: string, exists: bool, rows?: array<string, ?string>, url?: ?string, urlLabel?: ?string}|null
     */
    private function subjectDetails(UserActivity $activity): ?array
    {
        if (! $activity->subject_type) {
            return null;
        }

        $subject = $activity->subject;
        $type = Str::headline(class_basename($activity->subject_type));

        if (! $subject) {
            return ['type' => $type, 'exists' => false];
        }

        return match (true) {
            $subject instanceof Delineation => [
                'type' => 'Delineation',
                'exists' => true,
                'rows' => [
                    'Name' => $subject->name,
                    'Owner' => $subject->user?->name,
                    'Review status' => $subject->is_approved
                        ? ($subject->isHiddenFromMap() ? 'Approved (hidden from map by an admin)' : 'Approved')
                        : ($subject->is_rejected ? 'Rejected' : 'Pending review'),
                    'Reviewed' => ($subject->approved_at ?? $subject->rejected_at)?->format('M j, Y g:i A'),
                    'Rejection notes' => $subject->rejection_notes,
                    'Created' => $subject->created_at?->format('M j, Y g:i A'),
                ],
            ],
            $subject instanceof Analysis => [
                'type' => 'Analysis',
                'exists' => true,
                'rows' => [
                    'Type' => ucwords(str_replace('_', ' ', (string) $subject->analysis_type)),
                    'Status' => ucfirst((string) $subject->status),
                    'Result' => $subject->species_detected,
                    'Confidence' => $subject->classification_confidence !== null
                        ? round($subject->classification_confidence * ($subject->classification_confidence <= 1 ? 100 : 1), 1) . '%'
                        : null,
                    'Created' => $subject->created_at?->format('M j, Y g:i A'),
                ],
            ],
            $subject instanceof User => [
                'type' => 'User',
                'exists' => true,
                'rows' => [
                    'Name' => $subject->name,
                    'Email' => $subject->email,
                    'Role' => $subject->roleLabel(),
                ],
                'url' => route('admin.users.show', $subject),
                'urlLabel' => 'Open user',
            ],
            $subject instanceof DroneImageryLayer => [
                'type' => 'Drone Imagery Layer',
                'exists' => true,
                'rows' => [
                    'Name' => $subject->name,
                    'Uploaded by' => $subject->user?->name,
                    'Processing' => ucfirst((string) $subject->status),
                    'AI segmentation' => $subject->segmentation_status ? ucfirst($subject->segmentation_status) : null,
                    'Uploaded' => $subject->created_at?->format('M j, Y g:i A'),
                ],
                'url' => route('admin.map-layers.index', ['tab' => 'imagery', 'search' => $subject->name]),
                'urlLabel' => 'Open in Map Layers',
            ],
            default => [
                'type' => $type,
                'exists' => true,
                'rows' => ['Record ID' => (string) $subject->getKey()],
            ],
        };
    }
}
