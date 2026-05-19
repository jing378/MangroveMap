<?php

namespace App\Notifications;

use App\Models\Delineation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DelineationApproved extends Notification
{
    use Queueable;

    public function __construct(public Delineation $delineation) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Delineation Approved')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('Your delineation "' . $this->delineation->name . '" has been approved by an expert.')
            ->line('It is now visible on the public community map.')
            ->action('View Dashboard', route('dashboard'))
            ->line('Thank you for contributing to MangroveMap!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Delineation approved',
            'message' => 'Your delineation "' . $this->delineation->name . '" was approved and is now on the public map.',
            'type' => 'delineation_approved',
            'icon' => 'bi-check-circle',
            'delineation_id' => $this->delineation->id,
        ];
    }
}
