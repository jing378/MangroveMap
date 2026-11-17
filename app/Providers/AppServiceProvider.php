<?php

namespace App\Providers;

use App\Notifications\Channels\DevicePushChannel;
use Illuminate\Auth\Events\Logout;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Notifications list 'webpush' in via() to reach the user's phones and computers.
        $this->app->make(ChannelManager::class)->extend('webpush', fn () => new DevicePushChannel);

        // Logging out stops this browser's notifications, so the next person on a shared
        // device doesn't get them. Turning them back on is one tap on My Profile.
        Event::listen(Logout::class, function (Logout $event) {
            $endpoint = session('push_endpoint');

            if ($endpoint && $event->user && method_exists($event->user, 'deletePushSubscription')) {
                $event->user->deletePushSubscription($endpoint);
            }

            session()->forget('push_endpoint');
        });
    }
}
