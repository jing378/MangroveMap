<?php

namespace App\Notifications;

use App\Support\NotificationChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent to a delineation's owner when an expert deletes it (the record no longer exists). */
class DelineationRemoved extends Notification
{
    use Queueable;

    /** @param string $status Review state when it was removed: approved, pending or rejected. */
    public function __construct(public string $delineationName, public string $status = 'rejected') {}

    public function via(object $notifiable): array
    {
        return NotificationChannels::databaseAndMail();
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(ucfirst($this->status) . ' Delineation Removed')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('Your ' . $this->status . ' delineation "' . $this->delineationName . '" was removed by an expert.')
            ->action('Open Map', route('delineate'))
            ->line('You can draw and submit a new delineation of the area at any time.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Delineation removed',
            'message' => 'Your ' . $this->status . ' delineation "' . $this->delineationName . '" was removed by an expert. You can draw and submit a new one.',
            'type' => 'delineation_removed',
            'icon' => 'bi-trash',
            'delineation_name' => $this->delineationName,
            'delineation_status' => $this->status,
            'url' => route('delineate'),
            'actionLabel' => 'Draw a new delineation',
        ];
    }
}
