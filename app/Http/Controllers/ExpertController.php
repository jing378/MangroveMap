<?php

namespace App\Http\Controllers;

use App\Models\Delineation;
use App\Models\UserActivity;
use App\Notifications\DelineationApproved;
use App\Notifications\DelineationRejected;
use App\Notifications\DelineationRemoved;
use App\Http\Controllers\EndUserController;
use App\Models\User;
use App\Services\BoundaryResolver;
use Illuminate\Http\Request;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class ExpertController extends Controller
{
    public function dashboard()
    {
        return app(EndUserController::class)->dashboard();
    }

    public function map()
    {
        return app(EndUserController::class)->map();
    }

    public function delineate()
    {
        return app(EndUserController::class)->delineate();
    }

    public function storeDelineation(Request $request, BoundaryResolver $boundaryResolver)
    {
        $data = $request->validate([
            'features' => ['required', 'array', 'min:1', 'max:20'],
            'features.*.type' => ['required', 'string', 'in:point,line,area'],
            'features.*.coords' => ['required', 'array', 'min:1'],
            'features.*.location' => ['nullable', 'string', 'max:255'],
            'features.*.genera' => ['nullable', 'array'],
            'features.*.genera.*' => ['string', 'max:100'],
            'name' => ['nullable', 'string', 'max:191'],
            'notes' => ['nullable', 'string'],
            'genera' => ['nullable', 'array'],
            'genera.*' => ['string', 'max:100'],
        ]);

        $features = collect($data['features'])->map(function ($feature) use ($data) {
            return [
                'type' => $feature['type'],
                'coords' => $feature['coords'],
                'genera' => $feature['genera'] ?? $data['genera'] ?? [],
            ];
        })->values()->all();
        EndUserController::assertFeaturesHaveGenera($features);
        $features = $boundaryResolver->resolveFeatures($features);

        $delineation = Delineation::create([
            'user_id' => $request->user()->id,
            'name' => $data['name'] ?? 'Saved delineation',
            'notes' => $data['notes'] ?? null,
            'features' => $features,
            'is_approved' => true,
            'approved_at' => now(),
            'approved_by' => $request->user()->id,
        ]);

        UserActivity::recordDelineationPublished($delineation, $request->user());

        return response()->json([
            'message' => 'Delineation saved and published to the map immediately.',
            'delineation' => $delineation,
        ]);
    }

    public function approve(Request $request, Delineation $delineation)
    {
        if ($delineation->is_approved) {
            $message = 'This delineation is already approved.';
            if (request()->expectsJson()) {
                return response()->json([
                    'message' => $message,
                    'unreadCount' => Auth::user()->unreadNotifications()->count(),
                ], 200);
            }
            return redirect()->route('expert.dashboard')->with('info', $message);
        }

        if ($delineation->is_rejected) {
            $message = 'This delineation was rejected. The resident must submit a new one.';
            if (request()->expectsJson()) {
                return response()->json([
                    'message' => $message,
                    'unreadCount' => Auth::user()->unreadNotifications()->count(),
                ], 200);
            }
            return redirect()->route('expert.dashboard')->with('error', $message);
        }

        $data = $request->validate([
            'planting_recommendation' => ['nullable', 'string', 'max:5000'],
        ]);

        $updates = [
            'is_approved' => true,
            'approved_at' => now(),
            'approved_by' => Auth::id(),
            'is_rejected' => false,
            'rejected_at' => null,
            'rejected_by' => null,
            'rejection_notes' => null,
        ];
        if (array_key_exists('planting_recommendation', $data)) {
            $updates['planting_recommendation'] = trim((string) $data['planting_recommendation']) ?: null;
        }
        $delineation->update($updates);

        $delineation->load('user');
        $this->notifyUserSafely($delineation->user, new DelineationApproved($delineation));

        UserActivity::recordDelineationApproved($delineation, Auth::user());

        // Mark any review notifications for this delineation as read for current expert
        Auth::user()->unreadNotifications
            ->filter(fn($n) => ($n->data['delineation_id'] ?? null) == $delineation->id)
            ->each->markAsRead();

        $unreadCount = Auth::user()->unreadNotifications()->count();
        $message = 'Delineation approved. The resident has been notified.';

        if (request()->expectsJson()) {
            return response()->json(['message' => $message, 'unreadCount' => $unreadCount], 200);
        }
        return redirect()
            ->route('expert.dashboard')
            ->with('success', $message);
    }

    public function reject(Request $request, Delineation $delineation)
    {
        if ($delineation->is_approved) {
            $message = 'Cannot reject an already approved delineation.';
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                    'unreadCount' => Auth::user()->unreadNotifications()->count(),
                ], 200);
            }
            return redirect()->route('expert.dashboard')->with('error', $message);
        }

        if ($delineation->is_rejected) {
            $message = 'This delineation is already rejected.';
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                    'unreadCount' => Auth::user()->unreadNotifications()->count(),
                ], 200);
            }
            return redirect()->route('expert.dashboard')->with('info', $message);
        }

        $data = $request->validate([
            'rejection_notes' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $delineation->update([
            'is_rejected' => true,
            'rejected_at' => now(),
            'rejected_by' => Auth::id(),
            'rejection_notes' => $data['rejection_notes'],
        ]);

        $delineation->load('user');
        $this->notifyUserSafely($delineation->user, new DelineationRejected($delineation));

        UserActivity::recordDelineationRejected($delineation, Auth::user());

        // Mark any review notifications for this delineation as read for current expert
        Auth::user()->unreadNotifications
            ->filter(fn($n) => ($n->data['delineation_id'] ?? null) == $delineation->id)
            ->each->markAsRead();

        $unreadCount = Auth::user()->unreadNotifications()->count();
        $message = 'Delineation rejected. The resident has been notified with your notes.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'unreadCount' => $unreadCount,
                'removable_after' => $delineation->rejectedRemovableAt()?->toIso8601String(),
            ], 200);
        }
        return redirect()
            ->route('expert.dashboard')
            ->with('success', $message);
    }

    /**
     * Experts may delete any delineation: approved, pending or rejected. A resident's rejected delineation is the one
     * exception: the resident keeps REJECTED_REMOVAL_GRACE_DAYS to revise and resubmit it before it can be removed.
     */
    public function destroyDelineation(Request $request, Delineation $delineation)
    {
        $expert = Auth::user();
        $isOwn = (int) $delineation->user_id === (int) $expert->id;

        if (! $isOwn && $delineation->is_rejected && ! $delineation->is_approved && ! $delineation->canBeRemovedByExpert()) {
            return response()->json([
                'message' => 'The resident still has time to revise this delineation. It can be removed after '
                    . $delineation->rejectedRemovableAt()->timezone(config('app.timezone'))->format('M j, Y g:i A') . '.',
            ], 422);
        }

        $delineation->load('user');
        $owner = $delineation->user;
        $name = $delineation->name ?: 'Delineation #' . $delineation->id;
        $status = $delineation->is_approved ? 'approved' : ($delineation->is_rejected ? 'rejected' : 'pending');

        // Logged before deleting so the entry can still name the record; the subject link then shows "deleted".
        UserActivity::record($expert, 'Removed ' . $status . ' delineation: ' . $name, 'Expert Review', 'rejected', $delineation);

        $delineation->delete();

        $notified = $owner && ! $isOwn;
        if ($notified) {
            $this->notifyUserSafely($owner, new DelineationRemoved($name, $status));
        }

        return response()->json([
            'message' => ucfirst($status) . ' delineation removed.' . ($notified ? ' The owner has been notified.' : ''),
        ]);
    }

    private function notifyUserSafely(?User $user, Notification $notification): void
    {
        if (! $user) {
            return;
        }

        try {
            $user->notify($notification);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
