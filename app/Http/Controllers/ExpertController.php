<?php

namespace App\Http\Controllers;

use App\Models\Delineation;
use App\Notifications\DelineationApproved;
use App\Notifications\DelineationRejected;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ExpertController extends Controller
{
    public function dashboard()
    {
        return view('expert.dashboard', [
            'pendingDelineations' => Delineation::with('user:id,name,email,organization')
                ->pending()
                ->latest()
                ->get(),
            'recentlyApproved' => Delineation::with(['user:id,name', 'approvedBy:id,name'])
                ->approved()
                ->latest('approved_at')
                ->take(10)
                ->get(),
            'recentlyRejected' => Delineation::with(['user:id,name', 'rejectedBy:id,name'])
                ->rejected()
                ->latest('rejected_at')
                ->take(10)
                ->get(),
        ]);
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

        return response()->json([
            'message' => 'Delineation saved and published to the map immediately.',
            'delineation' => $delineation,
        ]);
    }

    public function approve(Delineation $delineation)
    {
        if ($delineation->is_approved) {
            return redirect()->route('expert.dashboard')->with('info', 'This delineation is already approved.');
        }

        if ($delineation->is_rejected) {
            return redirect()->route('expert.dashboard')->with('error', 'This delineation was rejected. The resident must submit a new one.');
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

        return redirect()
            ->route('expert.dashboard')
            ->with('success', 'Delineation approved. The resident has been notified.');
    }

    public function reject(Request $request, Delineation $delineation)
    {
        if ($delineation->is_approved) {
            return redirect()->route('expert.dashboard')->with('error', 'Cannot reject an already approved delineation.');
        }

        if ($delineation->is_rejected) {
            return redirect()->route('expert.dashboard')->with('info', 'This delineation is already rejected.');
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

        return redirect()
            ->route('expert.dashboard')
            ->with('success', 'Delineation rejected. The resident has been notified with your notes.');
    }
}
