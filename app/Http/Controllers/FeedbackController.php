<?php

namespace App\Http\Controllers;

use App\Models\Feedback;
use App\Models\User;
use App\Models\UserActivity;
use App\Notifications\AdminAlert;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FeedbackController extends Controller
{
    /** "Send Feedback" pop-up in the sidebar (every signed-in user). */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(array_keys(Feedback::TYPES))],
            'message' => ['required', 'string', 'min:5', 'max:5000'],
            'page_url' => ['nullable', 'string', 'max:2048'],
            'screenshot' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
        ], [
            'type.required' => 'Choose what kind of feedback this is.',
            'message.required' => 'Please describe your feedback.',
            'message.min' => 'Please write a little more so we can understand.',
            'screenshot.image' => 'The screenshot must be an image.',
            'screenshot.mimes' => 'The screenshot must be a JPG, PNG, or WebP file.',
            'screenshot.max' => 'The screenshot must be 5 MB or smaller.',
        ]);

        $user = $request->user();

        $feedback = Feedback::create([
            'user_id' => $user->id,
            'type' => $validated['type'],
            'message' => trim($validated['message']),
            'page_url' => $this->pagePath($validated['page_url'] ?? null),
            'screenshot_path' => $request->hasFile('screenshot')
                ? $request->file('screenshot')->store('feedback', Feedback::SCREENSHOT_DISK)
                : null,
            'status' => 'new',
        ]);

        UserActivity::record($user, 'Sent feedback: ' . $feedback->typeLabel(), 'Feedback', 'success', $feedback);
        $this->notifyAdmins($feedback, $user);

        return response()->json(['message' => 'Thank you! Your feedback was sent.'], 201);
    }

    public function index(Request $request)
    {
        // Bad filter values are dropped instead of failing the page.
        $filters = Validator::make($request->query(), [
            'search' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', Rule::in(array_keys(Feedback::TYPES))],
            'status' => ['nullable', Rule::in(array_keys(Feedback::STATUSES))],
        ])->valid();

        $feedback = Feedback::query()
            ->with('user:id,name,email,role')
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $like = '%' . addcslashes($search, '%_\\') . '%';
                $query->where(fn (Builder $query) => $query
                    ->where('message', 'like', $like)
                    ->orWhereHas('user', fn (Builder $user) => $user
                        ->where('name', 'like', $like)
                        ->orWhere('email', 'like', $like)));
            })
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->latest()
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.feedback', [
            'feedback' => $feedback,
            'filters' => $filters,
            'counts' => Feedback::query()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
            'totalWeek' => Feedback::where('created_at', '>=', now()->subWeek())->count(),
        ]);
    }

    public function update(Request $request, Feedback $feedback): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys(Feedback::STATUSES))],
        ]);

        $feedback->update(['status' => $validated['status']]);

        return back()->with('success', 'Feedback marked as ' . strtolower($feedback->statusLabel()) . '.');
    }

    public function destroy(Feedback $feedback): RedirectResponse
    {
        $feedback->delete();

        return back()->with('success', 'Feedback deleted.');
    }

    public function screenshot(Feedback $feedback): StreamedResponse
    {
        $disk = Storage::disk(Feedback::SCREENSHOT_DISK);
        abort_unless($feedback->screenshot_path && $disk->exists($feedback->screenshot_path), 404);

        return $disk->response($feedback->screenshot_path);
    }

    /** Keep only the path and query of the page the user was on, and only for this site. */
    private function pagePath(?string $url): ?string
    {
        if (blank($url)) {
            return null;
        }

        $parts = parse_url($url);
        if ($parts === false) {
            return null;
        }

        $host = $parts['host'] ?? null;
        $ownHosts = array_filter([request()->getHost(), parse_url((string) config('app.url'), PHP_URL_HOST)]);
        if ($host !== null && ! in_array(strtolower($host), array_map('strtolower', $ownHosts), true)) {
            return null;
        }

        $path = '/' . ltrim($parts['path'] ?? '', '/');
        if (! empty($parts['query'])) {
            $path .= '?' . $parts['query'];
        }

        return Str::limit($path, 2000, '');
    }

    private function notifyAdmins(Feedback $feedback, User $sender): void
    {
        try {
            Notification::send(
                User::where('role', 'admin')->get(),
                new AdminAlert(
                    'New feedback: ' . $feedback->typeLabel(),
                    Str::limit($feedback->message, 160),
                    'feedback',
                    route('admin.feedback.index'),
                    'View feedback',
                    details: [
                        'submitted_by' => $sender->name . ' (' . $sender->roleLabel() . ')',
                        'page' => $feedback->page_url,
                    ],
                ),
            );
        } catch (\Throwable $exception) {
            // The feedback is saved either way; admins still see it on the Feedback page.
            logger()->error('Failed to notify admins of new feedback', [
                'feedback_id' => $feedback->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
