# Notification Icons Guide

Your notifications now support beautiful icons in both the admin dashboard and the notification center!

## 🎨 Icon System Overview

Icons are automatically assigned based on notification type, or you can specify them manually when creating notifications.

## 📋 Supported Icon Types

The system includes automatic icons for these notification types:

| Type                 | Icon                         | Color    | Use Case                             |
| -------------------- | ---------------------------- | -------- | ------------------------------------ |
| `new_user`           | 👤 `bi-person-plus`          | Blue     | When a new user registers            |
| `new_analysis`       | 📈 `bi-graph-up`             | Green    | When a new analysis is created       |
| `analysis_completed` | ✅ `bi-check-circle`         | Green    | When analysis completes successfully |
| `analysis_failed`    | ⚠️ `bi-exclamation-triangle` | Red      | When analysis fails                  |
| `system_error`       | ❌ `bi-exclamation-circle`   | Dark Red | System errors                        |
| `system_alert`       | 🔔 `bi-bell`                 | Amber    | General system alerts                |
| `data_update`        | 🔄 `bi-arrow-repeat`         | Cyan     | When data is updated                 |
| `user_action`        | ✓ `bi-check-circle`          | Purple   | For user actions                     |

## 🚀 Creating Notifications with Icons

### Example 1: Notify User of Analysis Completion (Auto Icon)

```php
use App\Notifications\AnalysisCompleted;

$user->notify(new AnalysisCompleted('My Analysis', 'analysis-123'));
// Automatically uses: icon = 'bi-check-circle'
```

### Example 2: Admin Alert with Auto Icon

```php
use App\Notifications\AdminAlert;
use Illuminate\Support\Facades\Notification;

// Auto icon based on type
$admin = User::where('role', 'admin')->first();
$admin->notify(new AdminAlert(
    title: 'New User Registration',
    message: 'User John Doe has registered',
    type: 'new_user'  // Will use bi-person-plus icon
));
```

### Example 3: Admin Alert with Custom Icon

```php
$admin->notify(new AdminAlert(
    title: 'Custom Alert',
    message: 'Something special happened',
    type: 'system_alert',
    actionUrl: route('admin.dashboard'),
    actionLabel: 'View Dashboard',
    icon: 'bi-star-fill'  // Custom Bootstrap Icon
));
```

### Example 4: Notify Multiple Admins

```php
$admins = User::where('role', 'admin')->get();
Notification::send($admins, new AdminAlert(
    title: 'Analysis Failed',
    message: 'Analysis ID 456 failed due to timeout',
    type: 'analysis_failed'  // Will use bi-exclamation-triangle icon
));
```

## 🎯 Available Bootstrap Icons

You can use any Bootstrap Icon from [Bootstrap Icons](https://icons.getbootstrap.com/):

Common examples:

- `bi-exclamation-triangle` - Warning
- `bi-check-circle` - Success
- `bi-info-circle` - Information
- `bi-x-circle` - Error
- `bi-arrow-right` - Navigation
- `bi-download` - Download
- `bi-upload` - Upload
- `bi-trash` - Delete
- `bi-pencil` - Edit

## 📍 Where Icons Appear

### 1. Admin Dashboard

The "Recent System Notifications" section displays:

- **Icon** on the left (colored based on type)
- **Title** of the notification
- **Message** content
- **Type badge** with icon
- **Timestamp** (relative time)
- **Unread indicator** (green dot on unread notifications)

### 2. Notification Center

Visit `/notifications` to see all notifications with:

- **Large colored icon** on the left
- **Full notification details**
- **Type badge** with icon
- **Action buttons** with icons (Mark Read, Delete, View Details)

### 3. Notification Bell Dropdown

Quick preview in the navbar shows:

- **Icon** next to title
- **Quick message preview**
- **Recent 5 notifications**

## 🎨 Icon Colors in Admin Dashboard

Colors automatically match the notification type:

```
new_user          → Blue (#3b82f6)
new_analysis      → Green (#10b981)
analysis_failed   → Red (#ef4444)
system_error      → Dark Red (#dc2626)
system_alert      → Amber (#f59e0b)
data_update       → Cyan (#06b6d4)
user_action       → Purple (#8b5cf6)
analysis_completed → Green (#10b981)
```

## 💡 Best Practices

1. **Use Consistent Types**: Stick to the defined types for better UI consistency
2. **Descriptive Titles**: Keep titles short and clear
3. **Clear Messages**: Make messages actionable and understandable
4. **Action URLs**: Include action URLs for important notifications
5. **Custom Icons**: Only use custom icons when the default ones don't fit your needs

## 🔧 Customizing Icons

### Adding a New Icon Type

Edit `app/Notifications/AdminAlert.php` and add to the `getIconForType()` method:

```php
private function getIconForType(string $type): string
{
    return match($type) {
        'new_user' => 'bi-person-plus',
        // ... existing types ...
        'my_custom_type' => 'bi-custom-icon',  // Add here
        default => 'bi-info-circle',
    };
}
```

### Changing Icon Colors

Edit `resources/views/notifications/index.blade.php` or `resources/views/admin/dashboard.blade.php` and update the `$colorMap` array:

```php
$colorMap = [
    'new_user' => '#your-color-hex',
    // ... other types ...
];
```

## 🐛 Troubleshooting

**Icons not showing?**

- Make sure Bootstrap Icons CSS is loaded in your layout
- Check that the icon class name is correct (e.g., `bi-check-circle`)
- Verify the notification data includes the `icon` field

**Wrong colors?**

- Check the color mapping in the view files
- Ensure the notification type matches a key in the `$colorMap` array
- Default color (#6b7280 gray) is used if type is not found

**Missing notifications?**

- Verify notifications have `database` channel enabled
- Check your notification routing
- Ensure users are authenticated to view notifications

## 📚 Example: Complete Workflow

```php
// In a controller or service
use App\Notifications\AdminAlert;
use Illuminate\Support\Facades\Notification;

class AnalysisService
{
    public function completeAnalysis($analysis)
    {
        // ... analysis logic ...

        // Notify user
        $analysis->user->notify(new AnalysisCompleted(
            $analysis->title,
            $analysis->id
        ));

        // Notify admins
        $admins = User::where('role', 'admin')->get();
        Notification::send($admins, new AdminAlert(
            title: 'Analysis Complete',
            message: "Analysis '{$analysis->title}' completed successfully",
            type: 'analysis_completed',
            actionUrl: route('admin.dashboard'),
            actionLabel: 'View Results'
        ));
    }
}
```

That's it! Your notifications now have beautiful icons that improve the user experience.
