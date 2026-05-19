# Notifications Setup Guide

Your project now has database and email notifications set up. Here's how to use them:

## 1. USER ALERTS (AnalysisCompleted)

### Send to a user when their analysis is complete:

```php
// In a Controller or Job
use App\Models\User;
use App\Notifications\AnalysisCompleted;

// Example: In ClassifyController after analysis completes
$user = Auth::user();
$analysis = Analysis::find($analysisId);

$user->notify(new AnalysisCompleted(
    $analysis->title ?? 'Your Analysis',
    $analysis->id
));
```

### Send to multiple users:

```php
use App\Models\User;
use App\Notifications\AnalysisCompleted;
use Illuminate\Support\Facades\Notification;

$users = User::where('role', 'end_user')->get();

Notification::send($users, new AnalysisCompleted(
    'New Feature Available',
    'feature_1'
));
```

## 2. ADMIN ALERTS (AdminAlert)

### Send to all admins when something important happens:

```php
// Example: New user registered, new analysis, etc.
use App\Models\User;
use App\Notifications\AdminAlert;
use Illuminate\Support\Facades\Notification;

// Get all admin users
$admins = User::where('role', 'admin')->get();

Notification::send($admins, new AdminAlert(
    title: 'New User Registered',
    message: 'A new user ' . $newUser->name . ' has joined the platform.',
    type: 'new_user',
    actionUrl: '/admin/users/' . $newUser->id,
    actionLabel: 'View User'
));
```

### Send to a specific admin:

```php
$admin = User::where('role', 'admin')->first();

$admin->notify(new AdminAlert(
    title: 'Analysis Completed',
    message: 'The queued analysis for ' . $user->name . ' is now complete.',
    type: 'analysis_complete',
    actionUrl: '/admin/analyses',
    actionLabel: 'View Analyses'
));
```

## 3. EXAMPLE CONTROLLER IMPLEMENTATION

Update ClassifyController.php:

```php
public function store(Request $request)
{
    $request->validate([
        'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:10240'
    ]);

    $path = $request->file('image')->store('classifications', 'public');

    $analysis = Analysis::create([
        'user_id' => Auth::id(),
        'image_url' => Storage::url($path),
        'analysis_type' => 'classification',
        'status' => 'processing'
    ]);

    // NOTIFY ADMINS of new analysis
    $admins = User::where('role', 'admin')->get();
    Notification::send($admins, new AdminAlert(
        title: 'New Analysis Submitted',
        message: 'User ' . Auth::user()->name . ' has submitted a new analysis.',
        type: 'new_analysis',
        actionUrl: '/admin/analyses/' . $analysis->id,
        actionLabel: 'View Analysis'
    ));

    return redirect()->route('classify.results', $analysis->id);
}

public function completeAnalysis(Analysis $analysis, $results)
{
    $analysis->update([
        'results' => $results,
        'status' => 'completed'
    ]);

    // NOTIFY USER of completion
    $analysis->user->notify(new AnalysisCompleted(
        $analysis->analysis_type . ' Analysis',
        $analysis->id
    ));

    // NOTIFY ADMINS
    $admins = User::where('role', 'admin')->get();
    Notification::send($admins, new AdminAlert(
        title: 'Analysis Completed',
        message: 'Analysis for user ' . $analysis->user->name . ' is complete.',
        type: 'analysis_completed',
        actionUrl: '/admin/analyses/' . $analysis->id
    ));
}
```

## 4. DISPLAY NOTIFICATIONS IN VIEWS

### Show unread notification count in navigation:

```blade
@if(Auth::check())
    <span class="notification-badge">
        {{ Auth::user()->unreadNotifications->count() }}
    </span>
@endif
```

### List all notifications:

```blade
<div class="notifications">
    @foreach(Auth::user()->notifications as $notification)
        <div class="notification {{ $notification->read_at ? 'read' : 'unread' }}">
            <h4>{{ $notification->data['message'] ?? 'Notification' }}</h4>
            <p>{{ $notification->data['title'] ?? '' }}</p>
            <small>{{ $notification->created_at->diffForHumans() }}</small>
        </div>
    @endforeach
</div>
```

### Mark as read:

```blade
<form action="/notifications/{{ $notification->id }}/read" method="POST">
    @csrf
    <button>Mark as Read</button>
</form>
```

## 5. CREATE NOTIFICATION CONTROLLER (Optional)

Create `app/Http/Controllers/NotificationController.php`:

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = Auth::user()->notifications()->paginate(15);
        return view('notifications.index', ['notifications' => $notifications]);
    }

    public function markAsRead($id)
    {
        $notification = Auth::user()->notifications()->find($id);
        if ($notification) {
            $notification->markAsRead();
        }
        return back();
    }

    public function markAllAsRead()
    {
        Auth::user()->unreadNotifications->markAsRead();
        return back();
    }

    public function destroy($id)
    {
        Auth::user()->notifications()->find($id)?->delete();
        return back();
    }
}
```

## 6. ADD ROUTES

Add to `routes/web.php`:

```php
Route::middleware('auth')->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead'])->name('notifications.markAsRead');
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead'])->name('notifications.markAllAsRead');
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
});
```

## 7. QUEUE SETUP (Optional but Recommended)

For production, use a queue to send notifications in the background:

```bash
# Configure in .env
QUEUE_CONNECTION=database

# Create queue table
php artisan queue:table
php artisan migrate

# Run queue worker
php artisan queue:work
```

---

## Common Notification Types in Your App:

- **User**: Analysis completed, insights ready, system updates
- **Admin**: New user registered, new analysis submitted, system errors, quota warnings
