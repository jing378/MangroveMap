<?php

namespace App\Notifications;

use App\Models\Delineation;
use App\Support\NotificationChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DelineationSubmittedForReview extends Notification
{
    use Queueable;

    public function __construct(public Delineation $delineation, public bool $isRevision = false) {}

    public function via(object $notifiable): array
    {
        return ['database', ...NotificationChannels::push($notifiable)];
    }

    public function toArray(object $notifiable): array
    {
        // A drone re-survey of an area already on the map: approving it updates that area's boundary.
        $reassessed = $this->delineation->reassesses_delineation_id
            ? $this->delineation->reassessedDelineation
            : null;
        $reassessedName = $reassessed ? ($reassessed->name ?: "Delineation {$reassessed->id}") : null;

        return [
            'title' => $this->isRevision
                ? 'Delineation resubmitted for review'
                : ($reassessedName ? 'Drone re-survey submitted for review' : 'Delineation submitted for review'),
            'message' => match (true) {
                $reassessedName !== null => ($this->isRevision ? 'A revised drone re-survey' : 'A drone re-survey')
                    ." of \"{$reassessedName}\" is waiting for your review. Approving it updates that area's boundary.",
                $this->isRevision => 'A revised delineation has been resubmitted and is waiting for your review.',
                default => 'A new delineation has been submitted and is waiting for your review.',
            },
            'type' => 'delineation_submitted',
            'icon' => 'bi-hourglass-split',
            'delineation_id' => $this->delineation->id,
            'delineation_name' => $this->delineation->name,
            'submitted_by' => $this->delineation->user?->name,
            // The Notifications page looks up this person's email/phone so the expert can contact them.
            'submitted_by_id' => $this->delineation->user_id,
            'url' => $this->delineation->delineateUrlFor($notifiable),
            'actionLabel' => 'View delineation',
        ];
    }
}
