<?php

namespace App\Notifications;

use App\Models\Delineation;
use App\Support\NotificationChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells an area's owner that someone else's approved drone re-survey updated its boundary
 * (DelineationReassessment::apply), recorded as the observation of that year.
 */
class DelineationReassessed extends Notification
{
    use Queueable;

    public function __construct(
        public Delineation $area,
        public ?string $surveyedBy,
        public int $year,
        public float $previousHa,
        public float $currentHa,
    ) {}

    public function via(object $notifiable): array
    {
        return NotificationChannels::databaseAndMail($notifiable);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your mangrove area was re-surveyed')
            ->greeting('Hello '.$notifiable->name.',')
            ->line($this->sentence())
            ->line('The earlier boundary is kept in the area\'s "Changes over time".')
            ->action('View area', $this->area->delineateUrlFor($notifiable))
            ->line('Thank you for contributing to MangroveMap!');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Your area was re-surveyed',
            'message' => $this->sentence(),
            'type' => 'delineation_reassessed',
            'icon' => 'bi-arrow-repeat',
            'delineation_id' => $this->area->id,
            'delineation_name' => $this->name(),
            'url' => $this->area->delineateUrlFor($notifiable),
            'actionLabel' => 'View area',
        ];
    }

    private function name(): string
    {
        return $this->area->name ?: "Delineation {$this->area->id}";
    }

    private function sentence(): string
    {
        $by = $this->surveyedBy ? " by {$this->surveyedBy}" : '';

        return "A drone re-survey{$by} updated the boundary of \"{$this->name()}\" for {$this->year}: "
            .number_format($this->previousHa, 2).' ha → '.number_format($this->currentHa, 2).' ha.';
    }
}
