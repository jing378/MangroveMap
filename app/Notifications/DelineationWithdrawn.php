<?php

namespace App\Notifications;

use App\Models\User;
use App\Support\NotificationChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Sent to experts when a resident deletes their own delineation (the record no longer exists): a pending one
 * no longer needs review, and the expert who rejected one learns it was removed instead of revised. Also sent
 * when a resident deletes a drone upload, which removes the delineation made from it (even an approved one).
 */
class DelineationWithdrawn extends Notification
{
    use Queueable;

    /** @param string $status Review state when it was removed: pending, rejected, or approved (with its drone upload). */
    public function __construct(
        public string $delineationName,
        public string $status,
        public ?string $residentName = null,
    ) {}

    /**
     * Tell the experts concerned that a resident removed this delineation (call after deleting it): every expert for a
     * pending or approved one, the expert who rejected it (all experts when that is not recorded) for a rejected one.
     * A failed notification never fails the deletion.
     */
    public static function notifyExperts(string $name, string $status, ?int $rejectedBy, ?string $residentName): void
    {
        $experts = User::where('role', 'expert')
            ->when($status === 'rejected' && $rejectedBy, fn ($query) => $query->whereKey($rejectedBy))
            ->get();
        if ($experts->isEmpty() && $status === 'rejected') {
            $experts = User::where('role', 'expert')->get();
        }

        $notification = new self($name, $status, $residentName);
        $experts->each(function (User $expert) use ($notification) {
            try {
                $expert->notify($notification);
            } catch (\Throwable $e) {
                report($e);
            }
        });
    }

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
