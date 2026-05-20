<?php

namespace App\Http\Controllers;

use App\Models\Delineation;
use App\Models\UserActivity;
use App\Notifications\DelineationApproved;
use App\Notifications\DelineationRejected;
use App\Http\Controllers\EndUserController;
use Illuminate\Http\Request;
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
            'features' => ['required', 'array'],
            'name' => ['nullable', 'string', 'max:191'],
            'notes' => ['nullable', 'string'],
        ]);

        $delineation = Delineation::create([
            'user_id' => $request->user()->id,
            'name' => $data['name'] ?? 'Saved delineation',
            'notes' => $data['notes'] ?? null,
            'features' => $data['features'],
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
                return response()->json(['message' => $message, 'unreadCount' => 0], 200);
            }
            return redirect()->route('expert.dashboard')->with('info', $message);
        }

        if ($delineation->is_rejected) {
            $message = 'This delineation was rejected. The resident must submit a new one.';
            if (request()->expectsJson()) {
                return response()->json(['message' => $message, 'unreadCount' => 0], 200);
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
        $delineation->user?->notify(new DelineationApproved($delineation));

        UserActivity::recordDelineationApproved($delineation, Auth::user());

        $message = 'Delineation approved. The resident has been notified.';
        if (request()->expectsJson()) {
            return response()->json(['message' => $message, 'unreadCount' => 0], 200);
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
                return response()->json(['message' => $message, 'unreadCount' => 0], 200);
            }
            return redirect()->route('expert.dashboard')->with('error', $message);
        }

        if ($delineation->is_rejected) {
            $message = 'This delineation is already rejected.';
            if ($request->expectsJson()) {
                return response()->json(['message' => $message, 'unreadCount' => 0], 200);
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
        $delineation->user?->notify(new DelineationRejected($delineation));

        UserActivity::recordDelineationRejected($delineation, Auth::user());

        $message = 'Delineation rejected. The resident has been notified with your notes.';
        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'unreadCount' => 0], 200);
        }
        return redirect()
            ->route('expert.dashboard')
            ->with('success', $message);
    }
}
