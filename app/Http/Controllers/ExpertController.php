<?php

namespace App\Http\Controllers;

use App\Models\Delineation;
use App\Models\UserActivity;
use App\Notifications\DelineationApproved;
use App\Notifications\DelineationRejected;
use App\Http\Controllers\EndUserController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class ExpertController extends Controller
{
    public function dashboard()
    {
        return app(EndUserController::class)->dashboard();
    }

    public function storeDelineation(Request $request)
    {
        $data = $request->validate([
            'features' => ['required', 'array', 'min:1', 'max:20'],
            'features.*.type' => ['required', 'string', 'in:point,line,area'],
            'features.*.coords' => ['required', 'array', 'min:1'],
            'name' => ['nullable', 'string', 'max:191'],
            'notes' => ['nullable', 'string'],
        ]);

        $features = collect($data['features'])->map(function ($feature) {
            return [
                'type' => $feature['type'],
                'coords' => $feature['coords'],
            ];
        })->values()->all();

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

    public function approve(Delineation $delineation)
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

        $delineation->update([
            'is_approved' => true,
            'approved_at' => now(),
            'approved_by' => Auth::id(),
            'is_rejected' => false,
            'rejected_at' => null,
            'rejected_by' => null,
            'rejection_notes' => null,
        ]);

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
            return response()->json(['message' => $message, 'unreadCount' => $unreadCount], 200);
        }
        return redirect()
            ->route('expert.dashboard')
            ->with('success', $message);
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
