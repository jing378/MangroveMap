<?php

namespace App\Notifications;

use App\Models\Delineation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DelineationRejected extends Notification
{
    use Queueable;

    public function __construct(public Delineation $delineation) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Delineation Needs Revision')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('Your delineation "' . $this->delineation->name . '" was reviewed and needs changes before it can be published.');

        if ($this->delineation->rejection_notes) {
            $mail->line('Expert notes: ' . $this->delineation->rejection_notes);
        }

        return $mail
            ->action('View Dashboard', route('dashboard'))
            ->line('You can draw and submit a revised delineation from your dashboard.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Delineation rejected',
            'message' => 'Your delineation "' . $this->delineation->name . '" needs revision.'
                . ($this->delineation->rejection_notes ? ' Notes: ' . $this->delineation->rejection_notes : ''),
            'type' => 'delineation_rejected',
            'icon' => 'bi-x-circle',
            'delineation_id' => $this->delineation->id,
        ];
    }
}
