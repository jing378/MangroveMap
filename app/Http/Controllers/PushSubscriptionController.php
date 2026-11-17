<?php

namespace App\Http\Controllers;

use App\Notifications\Channels\DevicePushChannel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Device (Web Push) notifications: My Profile turns them on or off for the current browser.
 */
class PushSubscriptionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        abort_unless(DevicePushChannel::configured(), 503, 'Device notifications are not set up on this server.');

        $data = $request->validate([
            'endpoint' => ['required', 'string', 'url', 'max:500', 'starts_with:https://'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'contentEncoding' => ['nullable', 'string', 'in:aesgcm,aes128gcm'],
        ]);

        $request->user()->updatePushSubscription(
            $data['endpoint'],
            $data['keys']['p256dh'],
            $data['keys']['auth'],
            $data['contentEncoding'] ?? 'aes128gcm',
        );

        // Remembered so logging out turns this browser's notifications off (AppServiceProvider).
        $request->session()->put('push_endpoint', $data['endpoint']);

        return response()->json(['subscribed' => true]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'string', 'max:500']]);

        $request->user()->deletePushSubscription($data['endpoint']);

        if ($request->session()->get('push_endpoint') === $data['endpoint']) {
            $request->session()->forget('push_endpoint');
        }

        return response()->json(['subscribed' => false]);
    }
}
