<?php

namespace App\Notifications;

use App\Models\Delineation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DelineationSubmittedForReview extends Notification
{
    use Queueable;

    public function __construct(public Delineation $delineation) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Delineation submitted for review',
            'message' => 'A new delineation has been submitted and is waiting for your review.',
            'type' => 'delineation_submitted',
            'icon' => 'bi-hourglass-split',
            'delineation_id' => $this->delineation->id,
            'delineation_name' => $this->delineation->name,
            'submitted_by' => $this->delineation->user?->name,
            'url' => route('notifications.index'),
            'actionLabel' => 'Open notifications',
        ];
    }
}
