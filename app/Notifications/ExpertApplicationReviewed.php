<?php

namespace App\Notifications;

use App\Support\NotificationChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ExpertApplicationReviewed extends Notification
{
    use Queueable;

    public function __construct(public bool $approved, public ?string $notes = null) {}

    public function via(object $notifiable): array
    {
        return NotificationChannels::databaseAndMail();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->approved ? 'Expert Application Approved' : 'Expert Application Not Approved')
            ->greeting('Hello ' . $notifiable->name . ',');

        if ($this->approved) {
            return $mail
                ->line('Your application for an expert account has been approved.')
                ->line('You can now review resident delineations and add planting recommendations.')
                ->action('Open Expert Dashboard', route('expert.dashboard'));
        }

        $mail->line('Your application for an expert account was not approved.');

        if ($this->notes) {
            $mail->line('Reason: ' . $this->notes);
        }

        return $mail
            ->line('You can keep using MangroveMap with your resident account.')
            ->action('Open Dashboard', route('dashboard'));
    }

    public function toArray(object $notifiable): array
    {
        if ($this->approved) {
            return [
                'title' => 'Expert application approved',
                'message' => 'Your account is now an expert account. You can review delineations and add planting recommendations.',
                'type' => 'expert_application_approved',
                'icon' => 'bi-patch-check',
                'url' => route('expert.dashboard'),
                'actionLabel' => 'Open expert dashboard',
            ];
        }

        return [
            'title' => 'Expert application not approved',
            'message' => 'Your expert account application was not approved.'
                . ($this->notes ? ' Reason: ' . $this->notes : ''),
            'type' => 'expert_application_rejected',
            'icon' => 'bi-x-circle',
            'rejection_notes' => $this->notes,
        ];
    }
}
