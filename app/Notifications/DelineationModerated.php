<?php

namespace App\Notifications;

use App\Support\NotificationChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** An administrator hid, restored or deleted one of the user's delineations. */
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
        return NotificationChannels::databaseAndMail();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->title())
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line($this->message());

        return $mail->line('If you have questions, please contact a MangroveMap administrator.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'message' => $this->message(),
            'type' => 'delineation_' . $this->action,
            'icon' => match ($this->action) {
                'hidden' => 'bi-eye-slash',
                'restored' => 'bi-eye',
                default => 'bi-trash',
            },
            // Deleted records have no map location to open.
            'delineation_id' => $this->action === 'deleted' ? null : $this->delineationId,
            'delineation_name' => $this->delineationName,
            'reason' => $this->reason,
        ];
    }

    private function title(): string
    {
        return match ($this->action) {
            'hidden' => 'Delineation hidden from the map',
            'restored' => 'Delineation restored to the map',
            default => 'Delineation removed',
        };
    }

    private function message(): string
    {
        $message = match ($this->action) {
            'hidden' => 'An administrator hid your delineation "' . $this->delineationName . '" from the shared map.',
            'restored' => 'An administrator restored your delineation "' . $this->delineationName . '" to the shared map.',
            default => 'An administrator deleted your delineation "' . $this->delineationName . '".',
        };

        return $this->reason ? $message . ' Reason: ' . $this->reason : $message;
    }
}
