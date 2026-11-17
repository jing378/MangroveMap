<?php

namespace App\Support;

use App\Notifications\Channels\DevicePushChannel;

class NotificationChannels
{
    /**
     * In-app (database) notifications always; email only when mail is configured to send;
     * a push to the user's devices when they turned it on (see push()).
     */
    public static function databaseAndMail(?object $notifiable = null): array
    {
        $channels = ['database'];

        if (self::shouldSendMail()) {
            $channels[] = 'mail';
        }

        return [...$channels, ...self::push($notifiable)];
    }

    /**
     * ['webpush'] when the VAPID keys are set and the user turned on notifications on at least one device.
     */
    public static function push(?object $notifiable): array
    {
        if (! $notifiable || ! DevicePushChannel::configured() || ! method_exists($notifiable, 'pushSubscriptions')) {
            return [];
        }

        return $notifiable->pushSubscriptions()->exists() ? ['webpush'] : [];
    }

    public static function shouldSendMail(): bool
    {
        if (! filter_var(config('mail.notifications', true), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        $driver = config('mail.default');

        return ! in_array($driver, ['log', 'array'], true);
    }
}
