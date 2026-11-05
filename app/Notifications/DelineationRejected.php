<?php

namespace App\Notifications;

use App\Models\Delineation;
use App\Support\NotificationChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DelineationRejected extends Notification
{
    use Queueable;

    public function __construct(public Delineation $delineation) {}

    public function via(object $notifiable): array
    {
        return NotificationChannels::databaseAndMail();
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
            ->action('Revise Delineation', $this->reviseUrl())
            ->line('You can edit it and resubmit it for review from the Delineate page.')
            ->line($this->removalNotice());
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Delineation rejected',
            'message' => 'Your delineation "' . $this->delineation->name . '" needs revision.'
                . ($this->delineation->rejection_notes ? ' Notes: ' . $this->delineation->rejection_notes : '')
                . ' ' . $this->removalNotice(),
            'type' => 'delineation_rejected',
            'icon' => 'bi-x-circle',
            'delineation_id' => $this->delineation->id,
            'delineation_name' => $this->delineation->name,
            'url' => $this->delineation->mapDashboardUrlFor($notifiable),
            'actionLabel' => 'View on map',
            'removable_after' => $this->delineation->rejectedRemovableAt()?->toIso8601String(),
        ];
    }

    private function reviseUrl(): string
    {
        return route('delineate', ['delineation' => $this->delineation->id]);
    }

    private function removalNotice(): string
    {
        $removableAt = $this->delineation->rejectedRemovableAt();
        $deadline = $removableAt
            ? ' (' . $removableAt->timezone(config('app.timezone'))->format('M j, Y') . ')'
            : '';

        return 'If it is not revised within ' . Delineation::REJECTED_REMOVAL_GRACE_DAYS . ' days' . $deadline
            . ', experts may remove it.';
    }
}
