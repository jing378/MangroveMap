<?php

namespace App\Notifications;

use App\Support\NotificationChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Sent to experts when a resident deletes their own delineation (the record no longer exists): a pending one
 * no longer needs review, and the expert who rejected one learns it was removed instead of revised.
 */
class DelineationWithdrawn extends Notification
{
    use Queueable;

    /** @param string $status Review state when it was removed: pending or rejected. */
    public function __construct(
        public string $delineationName,
        public string $status,
        public ?string $residentName = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', ...NotificationChannels::push($notifiable)];
    }

    public function toArray(object $notifiable): array
    {
        $who = $this->residentName ?: 'A resident';

        return [
            'title' => 'Delineation removed by resident',
            'message' => $who.' removed their '.$this->status.' delineation "'.$this->delineationName.'".'
                .($this->status === 'pending' ? ' It no longer needs review.' : ''),
            'type' => 'delineation_withdrawn',
            'icon' => 'bi-trash',
            'delineation_name' => $this->delineationName,
            'delineation_status' => $this->status,
            'removed_by' => $this->residentName,
            'url' => route('expert.delineate'),
            'actionLabel' => 'Open delineations',
        ];
    }
}
