<?php

namespace App\Support;

class NotificationChannels
{
    /**
     * In-app (database) notifications always; email only when mail is configured to send.
     */
    public static function databaseAndMail(): array
    {
        $channels = ['database'];

        if (self::shouldSendMail()) {
            $channels[] = 'mail';
        }

        return $channels;
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
