<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $notifications = $user->notifications()->paginate(15);

        return view('notifications.index', [
            'notifications' => $notifications,
            'unreadCount' => $user->unreadNotifications->count()
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

    public function destroy($id)
    {
        $notification = Auth::user()->notifications()->find($id);

        if ($notification) {
            $notification->delete();
        }

        return back()->with('success', 'Notification deleted');
    }

    public function getUnreadCount()
    {
        return response()->json([
            'count' => Auth::user()->unreadNotifications->count()
        ]);
    }
}
