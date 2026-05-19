<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminAlert extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public string $title,
        public string $message,
        public string $type,
        public ?string $actionUrl = null,
        public ?string $actionLabel = 'View Details',
        public ?string $icon = null
    ) {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $mailMessage = (new MailMessage)
            ->subject('[Alert] ' . $this->title)
            ->greeting('Hello Admin,')
            ->line($this->message);

        if ($this->actionUrl) {
            $mailMessage->action($this->actionLabel, url($this->actionUrl));
        }

        return $mailMessage->line('This is an automated admin alert.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        // Auto-assign icon based on type if not provided
        $icon = $this->icon ?? $this->getIconForType($this->type);

        return [
            'title' => $this->title,
            'message' => $this->message,
            'type' => $this->type,
            'actionUrl' => $this->actionUrl,
            'actionLabel' => $this->actionLabel,
            'icon' => $icon,
        ];
    }

    /**
     * Get icon class based on notification type.
     */
    private function getIconForType(string $type): string
    {
        return match ($type) {
            'new_user' => 'bi-person-plus',
            'new_analysis' => 'bi-graph-up',
            'analysis_failed' => 'bi-exclamation-triangle',
            'system_error' => 'bi-exclamation-circle',
            'system_alert' => 'bi-bell',
            'data_update' => 'bi-arrow-repeat',
            'user_action' => 'bi-check-circle',
            default => 'bi-info-circle',
        };
    }
}
