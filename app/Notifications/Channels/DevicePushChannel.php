<?php

namespace App\Notifications\Channels;

use App\Models\Delineation;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Minishlink\WebPush\ContentEncoding;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use NotificationChannels\WebPush\ReportHandler;
use NotificationChannels\WebPush\WebPushMessage;
use Throwable;

/**
 * Sends a notification to the user's devices as a Web Push message ("webpush" channel).
 *
 * Every notification already stores title/message/url for the bell (toArray), so the push
 * is built from that: no notification needs its own toWebPush(). A failed push is logged and
 * never breaks the request or the other channels (the bell and email still arrive).
 */
class DevicePushChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! self::configured() || ! method_exists($notifiable, 'pushSubscriptions')) {
            return;
        }

        try {
            $subscriptions = $notifiable->pushSubscriptions()->get();

            if ($subscriptions->isEmpty()) {
                return;
            }

            $message = method_exists($notification, 'toWebPush')
                ? $notification->toWebPush($notifiable, $notification)
                : self::messageFromArray(
                    method_exists($notification, 'toArray') ? $notification->toArray($notifiable) : [],
                    $notifiable,
                    $notification->id ?? null,
                );

            $webPush = self::client();
            $payload = json_encode($message->toArray());

            foreach ($subscriptions as $subscription) {
                $webPush->queueNotification(new Subscription(
                    $subscription->endpoint,
                    $subscription->public_key,
                    $subscription->auth_token,
                    $subscription->content_encoding ?? ContentEncoding::aes128gcm,
                ), $payload, $message->getOptions());
            }

            $handler = app(ReportHandler::class);

            foreach ($webPush->flush() as $report) {
                $subscription = $subscriptions->firstWhere('endpoint', $report->getEndpoint());

                if ($subscription) {
                    // Deletes subscriptions the push service says are gone (browser data cleared, permission revoked).
                    $handler->handleReport($report, $subscription, $message);
                }

                if (! $report->isSuccess() && ! $report->isSubscriptionExpired()) {
                    Log::warning('Web push not delivered', ['reason' => $report->getReason()]);
                }
            }
        } catch (Throwable $e) {
            Log::warning('Web push failed: '.$e->getMessage());
        }
    }

    /** Push is on only when the VAPID keys are set in .env. */
    public static function configured(): bool
    {
        return filled(config('webpush.vapid.public_key')) && filled(config('webpush.vapid.private_key'));
    }

    public static function client(): WebPush
    {
        $auth = ['VAPID' => [
            'subject' => config('webpush.vapid.subject') ?: url('/'),
            'publicKey' => config('webpush.vapid.public_key'),
            'privateKey' => config('webpush.vapid.private_key'),
        ]];

        return (new WebPush($auth, ['TTL' => 86400], 8, config('webpush.client_options', [])))
            ->setReuseVAPIDHeaders(true)
            ->setAutomaticPadding(config('webpush.automatic_padding', true));
    }

    /**
     * Turns the bell notification's data into the phone notification.
     *
     * @param  array<string, mixed>  $data
     */
    public static function messageFromArray(array $data, object $notifiable, ?string $notificationId = null): WebPushMessage
    {
        $type = (string) ($data['type'] ?? 'notification');
        $title = $data['title'] ?? Str::headline($type);
        $body = trim((string) ($data['message'] ?? ''));

        if (! empty($data['analysisTitle']) && ! str_contains($body, (string) $data['analysisTitle'])) {
            $body = trim($body.': '.$data['analysisTitle']);
        }

        return (new WebPushMessage)
            ->title((string) $title)
            ->body(Str::limit($body, 220))
            ->icon('/icon-192.png')
            ->badge('/icon-192.png')
            ->tag($type.($notificationId ? '-'.$notificationId : ''))
            ->data(['url' => self::targetUrl($data, $notifiable)])
            ->options(['TTL' => 86400, 'urgency' => 'normal']);
    }

    /**
     * Where tapping the notification goes: the same page the bell's "View" link opens,
     * as a relative path so it works on whatever address the site is reached at.
     *
     * @param  array<string, mixed>  $data
     */
    public static function targetUrl(array $data, object $notifiable): string
    {
        if (! empty($data['delineation_id'])) {
            return Delineation::delineateUrlForId($data['delineation_id'], $notifiable, false);
        }

        $url = $data['url'] ?? $data['actionUrl'] ?? null;

        if (is_string($url) && $url !== '') {
            if (preg_match('#^https?://[^/]+(/.*)?$#i', $url, $match)) {
                return $match[1] ?? '/';
            }

            if (str_starts_with($url, '/')) {
                return $url;
            }
        }

        return route('notifications.index', [], false);
    }
}
