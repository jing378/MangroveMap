<?php

namespace App\Notifications;

use App\Support\NotificationChannels;
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
        public ?string $icon = null,
        /** Extra fields shown as detail lines on the notifications page (e.g. applicant_name, organization). */
        public array $details = [],
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
        return NotificationChannels::databaseAndMail();
    }

    /**
     * The in-app notification is stored right away so admins see it without a queue worker running;
     * only the email waits for the queue (so a slow mail server never holds up the request).
     *
     * @return array<string, string>
     */
    public function viaConnections(): array
    {
        return [
            'database' => 'sync',
        ];
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

        foreach ($this->details as $key => $value) {
            if (filled($value)) {
                $mailMessage->line(ucfirst(str_replace('_', ' ', $key)) . ': ' . $value);
            }
        }

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

        return array_merge($this->details, [
            'title' => $this->title,
            'message' => $this->message,
            'type' => $this->type,
            'actionUrl' => $this->actionUrl,
            'actionLabel' => $this->actionLabel,
            'icon' => $icon,
        ]);
    }

    /**
     * Get icon class based on notification type.
     */
    private function getIconForType(string $type): string
    {
        return match ($type) {
            'new_user' => 'bi-person-plus',
            'expert_application' => 'bi-person-badge',
            'new_analysis' => 'bi-graph-up',
            'analysis_failed' => 'bi-exclamation-triangle',
            'system_error' => 'bi-exclamation-circle',
            'system_alert' => 'bi-bell',
            'data_update' => 'bi-arrow-repeat',
            'user_action' => 'bi-check-circle',
            'feedback' => 'bi-chat-left-text',
            default => 'bi-info-circle',
        };
    }
}
