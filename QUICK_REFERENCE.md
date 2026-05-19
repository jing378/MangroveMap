# Notification System - Quick Reference

## ⚡ Copy-Paste Ready Code

### 1️⃣ Add to routes/web.php

```php
Route::middleware('auth')->group(function () {
    Route::get('/notifications', [\App\Http\Controllers\NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{id}/read', [\App\Http\Controllers\NotificationController::class, 'markAsRead'])->name('notifications.markAsRead');
    Route::post('/notifications/mark-all-read', [\App\Http\Controllers\NotificationController::class, 'markAllAsRead'])->name('notifications.markAllAsRead');
    Route::delete('/notifications/{id}', [\App\Http\Controllers\NotificationController::class, 'destroy'])->name('notifications.destroy');
});
```

### 2️⃣ Add to layouts/app.blade.php (in navbar)

```blade
@auth
    @include('components.notification-bell')
@endauth
```

### 3️⃣ Send notification to single user

```php
use App\Notifications\AnalysisCompleted;

Auth::user()->notify(new AnalysisCompleted('Analysis Title', $analysisId));
```

### 4️⃣ Send notification to all admins

```php
use App\Models\User;
use App\Notifications\AdminAlert;
use Illuminate\Support\Facades\Notification;

$admins = User::where('role', 'admin')->get();
Notification::send($admins, new AdminAlert(
    title: 'Alert Title',
    message: 'Alert message here',
    type: 'alert_type',
    actionUrl: '/dashboard',
    actionLabel: 'View'
));
```

### 5️⃣ Create custom notification

```bash
php artisan make:notification YourNotificationName
```

Then implement the `via()` and `toArray()` methods.

---

## 📊 Notification Types

| Type              | When to use                   | Example                               |
| ----------------- | ----------------------------- | ------------------------------------- |
| AnalysisCompleted | User's analysis is done       | "Your classification is ready"        |
| AdminAlert        | Admin needs to know something | "New user registered", "System error" |
| Custom            | Special cases                 | Create your own                       |

---

## 🔧 Common Tasks

### Get unread count

```blade
{{ Auth::user()->unreadNotifications->count() }}
```

### Get all notifications

```php
$notifications = Auth::user()->notifications()->latest()->get();
```

### Get unread notifications only

```php
$unread = Auth::user()->unreadNotifications;
```

### Mark notification as read

```php
$notification->markAsRead();
```

### Delete notification

```php
$notification->delete();
```

### Mark all as read

```php
Auth::user()->unreadNotifications->markAsRead();
```

---

## 💡 Best Practices

✅ DO:

- Send notifications asynchronously (use queues for production)
- Include action URLs when relevant
- Use descriptive titles and messages
- Mark as read when user views related page

❌ DON'T:

- Send too many notifications (spam)
- Send duplicate notifications
- Include sensitive data in notifications
- Use notifications for logging (use logs instead)

---

## 🧪 Testing

### View notifications in browser

```
http://yourapp.com/notifications
```

### Test in tinker

```bash
php artisan tinker

$user = User::first();
$user->notify(new AnalysisCompleted('Test', 1));
$user->notifications;
```

### Check database directly

```bash
php artisan tinker

DB::table('notifications')->latest()->get();
```

---

## 🆘 Troubleshooting

**Q: Notifications not showing?**

- Check routes are added to web.php
- Verify User model has `Notifiable` trait ✅
- Check database notifications table exists

**Q: Emails not sending?**

- Check .env MAIL\_\* settings
- Check `storage/logs/laravel.log` for errors
- Use MAIL_DRIVER=log in local dev

**Q: Notifications table not created?**

- Run: `php artisan migrate`

**Q: Can't find routes?**

- Check routes/web.php is correct
- Run: `php artisan route:list`

---

## 📚 Full Documentation

See: NOTIFICATIONS_SETUP.md for complete guide
See: NOTIFICATION_EXAMPLES.php for real-world examples
