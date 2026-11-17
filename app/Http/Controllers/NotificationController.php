<?php

namespace App\Http\Controllers;

use App\Notifications\Channels\DevicePushChannel;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class NotificationController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $notifications = $user->notifications()->paginate(15);

        return view('notifications.notifications', [
            'notifications' => $notifications,
            'unreadCount' => $user->unreadNotifications->count(),
        ]);
    }

    public function markAsRead(Request $request, $id)
    {
        $notification = Auth::user()->notifications()->find($id);

        if ($notification && ! $notification->read_at) {
            $notification->markAsRead();
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Notification marked as read',
                'unreadCount' => Auth::user()->unreadNotifications()->count(),
            ]);
        }

        return back()->with('success', 'Notification marked as read');
    }

    public function markAllAsRead(Request $request)
    {
        Auth::user()->unreadNotifications->markAsRead();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'unreadCount' => 0,
            ]);
        }

        return back()->with('success', 'All notifications marked as read');
    }

    public function destroy(Request $request, $id)
    {
        $notification = Auth::user()->notifications()->find($id);

        if ($notification) {
            $notification->delete();
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Notification deleted',
                'unreadCount' => Auth::user()->unreadNotifications()->count(),
            ]);
        }

        return back()->with('success', 'Notification deleted');
    }

    /**
     * Live updates (partials/live-notifications): every open page asks this every ~20 s while visible.
     * Returns the unread count and the notifications created since `since` (the `now` of the previous
     * answer, or of the page render), so new ones show up without a refresh.
     */
    public function poll(Request $request)
    {
        $user = Auth::user();
        $since = null;

        try {
            $since = $request->filled('since') ? Carbon::parse((string) $request->query('since')) : null;
        } catch (\Throwable) {
            $since = null;
        }

        $fresh = [];

        if ($since) {
            $fresh = $user->notifications()
                ->where('created_at', '>=', $since->copy()->subSecond())
                ->latest()
                ->limit(5)
                ->get()
                ->map(function ($notification) use ($user) {
                    $data = $notification->data;
                    $icon = (string) ($data['icon'] ?? '');

                    return [
                        'id' => $notification->id,
                        'title' => (string) ($data['title'] ?? Str::headline((string) ($data['type'] ?? 'Notification'))),
                        'message' => Str::limit((string) ($data['message'] ?? ''), 160),
                        'icon' => preg_match('/^bi-[a-z0-9-]+$/', $icon) ? $icon : 'bi-bell-fill',
                        'url' => DevicePushChannel::targetUrl($data, $user),
                        'read' => $notification->read_at !== null,
                    ];
                })
                ->values()
                ->all();
        }

        return response()->json([
            'unread' => $user->unreadNotifications()->count(),
            'fresh' => $fresh,
            'now' => now()->toIso8601String(),
        ]);
    }

    public function getUnreadCount()
    {
        return response()->json([
            'count' => Auth::user()->unreadNotifications->count(),
        ]);
    }
}
