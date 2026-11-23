<?php

namespace App\Http\Controllers;

use App\Models\AdminReminder;
use App\Services\AdminReminderSender;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/** "Send reminder" on the admin dashboard and in Map Layers (partials/admin-reminder-dialog). */
class AdminReminderController extends Controller
{
    public function store(Request $request, AdminReminderSender $sender): RedirectResponse
    {
        abort_if(Auth::user()->role !== 'admin', 403);

        $data = $request->validate([
            'kind' => ['required', Rule::in(AdminReminder::KINDS)],
            'target_id' => ['nullable', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $result = $sender->send(Auth::user(), $data['kind'], isset($data['target_id']) ? (int) $data['target_id'] : null, $data['note'] ?? null);

        // Back to the page (and list) the reminder was sent from.
        return redirect()->back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }
}
