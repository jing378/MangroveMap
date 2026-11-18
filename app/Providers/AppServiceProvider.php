<?php

namespace App\Providers;

use App\Notifications\Channels\DevicePushChannel;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
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
        $this->configureAuthRateLimits();

        // Notifications list 'webpush' in via() to reach the user's phones and computers.
        $this->app->make(ChannelManager::class)->extend('webpush', fn () => new DevicePushChannel);

        // Logging out stops this browser's notifications, so the next person on a shared
        // device doesn't get them. The browser remembers per account that they were on, and
        // partials/device-push-restore turns them back on when that user logs in again.
        Event::listen(Logout::class, function (Logout $event) {
            $endpoint = session('push_endpoint');

            if ($endpoint && $event->user && method_exists($event->user, 'deletePushSubscription')) {
                $event->user->deletePushSubscription($endpoint);
            }

            session()->forget('push_endpoint');
        });
    }

    /**
     * Per-IP limits on the login, sign-up and password forms (routes/web.php, throttle:<name>), against
     * password guessing and email flooding. The IP is the visitor's own: cloudflared on this machine sends
     * X-Forwarded-For, which bootstrap/app.php trusts from localhost. Generous enough for a class signing up
     * together on one school network. Login also has a stricter per-email limit in AuthenticatedSessionController.
     * Going over sends the user back to the form with a message instead of a bare 429 page.
     */
    private function configureAuthRateLimits(): void
    {
        $limits = [
            'login' => 30,          // attempts per minute per IP, across all emails
            'register' => 10,
            'password-email' => 5,  // the password broker also allows one email per address per 60 s
            'password-reset' => 10,
        ];

        foreach ($limits as $name => $perMinute) {
            RateLimiter::for($name, fn (Request $request) => Limit::perMinute($perMinute)
                ->by($name.'|'.$request->ip())
                ->response(function (Request $request, array $headers) {
                    $seconds = (int) ($headers['Retry-After'] ?? 60);
                    $message = "Too many attempts. Please wait {$seconds} seconds and try again.";

                    if ($request->expectsJson()) {
                        return response()->json(['message' => $message], 429, $headers);
                    }

                    return back()
                        ->withInput($request->except(['password', 'password_confirmation']))
                        ->withErrors(['email' => $message]);
                }));
        }
    }
}
