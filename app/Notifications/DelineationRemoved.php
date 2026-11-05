<?php

namespace App\Notifications;

use App\Support\NotificationChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent to a resident when an expert deletes their rejected delineation (the record no longer exists). */
class DelineationRemoved extends Notification
{
    use Queueable;

    public function __construct(public string $delineationName) {}

    public function via(object $notifiable): array
    {
        return NotificationChannels::databaseAndMail();
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Rejected Delineation Removed')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('Your rejected delineation "' . $this->delineationName . '" was removed by an expert.')
            ->action('Open Map', route('delineate'))
            ->line('You can draw and submit a new delineation of the area at any time.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Delineation removed',
            'message' => 'Your rejected delineation "' . $this->delineationName . '" was removed by an expert. You can draw and submit a new one.',
            'type' => 'delineation_removed',
            'icon' => 'bi-trash',
            'delineation_name' => $this->delineationName,
            'url' => route('delineate'),
            'actionLabel' => 'Draw a new delineation',
        ];
    }
}
