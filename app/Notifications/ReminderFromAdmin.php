<?php

namespace App\Notifications;

use App\Support\NotificationChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A reminder an admin sent (AdminReminderSender): experts about delineations waiting for review, or a
 * resident about a drone survey waiting for them to confirm its mangrove areas. Bell, email and phone push;
 * "View" opens `url`.
 */
class ReminderFromAdmin extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $message,
        public string $url,
        public string $actionLabel,
        public ?string $note = null,
        public ?string $adminName = null,
    ) {}

    public function via(object $notifiable): array
    {
        return NotificationChannels::databaseAndMail($notifiable);
    }

    /** The bell item and the push go out straight away (no queue worker needed); only the email waits for the queue. */
    public function viaConnections(): array
    {
        return [
            'database' => 'sync',
            'webpush' => 'sync',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Reminder: '.$this->title)
            ->greeting('Hello '.$notifiable->name.',')
            ->line($this->message);

        if ($this->note) {
            $mail->line('Note from '.($this->adminName ?: 'the administrator').': '.$this->note);
        }

        return $mail->action($this->actionLabel, url($this->url))
            ->line('You received this reminder from a MangroveMap administrator.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Reminder: '.$this->title,
            'message' => $this->note
                ? $this->message.' Note from '.($this->adminName ?: 'the administrator').': '.$this->note
                : $this->message,
            'type' => 'admin_reminder',
            'icon' => 'bi-alarm',
            'url' => $this->url,
            'actionLabel' => $this->actionLabel,
        ];
    }
}
