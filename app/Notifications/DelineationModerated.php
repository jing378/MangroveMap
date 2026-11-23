<?php

namespace App\Notifications;

use App\Support\NotificationChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * An administrator deleted one of the user's delineations (from Map Layers). Older stored notifications may
 * still say "hidden" or "restored" from when admins could hide delineations; they keep their saved text.
 */
class DelineationModerated extends Notification
{
    use Queueable;

    public function __construct(
        public string $action,
        public string $delineationName,
        public ?int $delineationId = null,
        public ?string $reason = null,
    ) {}

    public function via(object $notifiable): array
    {
        return NotificationChannels::databaseAndMail($notifiable);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->title())
            ->greeting('Hello '.$notifiable->name.',')
            ->line($this->message());

        return $mail->line('If you have questions, please contact a MangroveMap administrator.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'message' => $this->message(),
            'type' => 'delineation_'.$this->action,
            'icon' => 'bi-trash',
            // Deleted records have no map location to open.
            'delineation_id' => null,
            'delineation_name' => $this->delineationName,
            'reason' => $this->reason,
        ];
    }

    private function title(): string
    {
        return 'Delineation removed';
    }

    private function message(): string
    {
        $message = 'An administrator deleted your delineation "'.$this->delineationName.'".';

        return $this->reason ? $message.' Reason: '.$this->reason : $message;
    }
}
