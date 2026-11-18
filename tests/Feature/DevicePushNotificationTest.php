<?php

namespace Tests\Feature;

use App\Models\Delineation;
use App\Models\User;
use App\Notifications\AdminAlert;
use App\Notifications\Channels\DevicePushChannel;
use App\Notifications\DelineationApproved;
use App\Notifications\DelineationRemoved;
use App\Notifications\DelineationSubmittedForReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DevicePushNotificationTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = 'https://fcm.googleapis.com/fcm/send/test-endpoint';

    protected function setUp(): void
    {
        parent::setUp();

        // Any non-empty keys turn the feature on; nothing is really sent in these tests.
        config([
            'webpush.vapid.public_key' => 'test-public-key',
            'webpush.vapid.private_key' => 'test-private-key',
        ]);
    }

    private function subscribe(User $user, string $endpoint = self::ENDPOINT): void
    {
        $user->updatePushSubscription($endpoint, 'p256dh-key', 'auth-token', 'aes128gcm');
    }

    public function test_user_can_turn_on_and_off_notifications_for_a_device(): void
    {
        $user = User::factory()->create(['role' => 'end_user']);

        $this->actingAs($user)->postJson(route('push-subscriptions.store'), [
            'endpoint' => self::ENDPOINT,
            'keys' => ['p256dh' => 'p256dh-key', 'auth' => 'auth-token'],
            'contentEncoding' => 'aes128gcm',
        ])->assertOk()->assertJson(['subscribed' => true]);

        $this->assertSame([self::ENDPOINT], $user->pushSubscriptions()->pluck('endpoint')->all());

        $this->actingAs($user)->deleteJson(route('push-subscriptions.destroy'), ['endpoint' => self::ENDPOINT])
            ->assertOk()->assertJson(['subscribed' => false]);

        $this->assertSame(0, $user->pushSubscriptions()->count());
    }

    public function test_subscription_needs_an_https_endpoint_and_keys(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('push-subscriptions.store'), [
            'endpoint' => 'http://insecure.example/push',
        ])->assertUnprocessable()->assertJsonValidationErrors(['endpoint', 'keys.p256dh', 'keys.auth']);
    }

    public function test_subscribing_is_refused_when_the_server_has_no_keys(): void
    {
        config(['webpush.vapid.public_key' => null]);
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('push-subscriptions.store'), [
            'endpoint' => self::ENDPOINT,
            'keys' => ['p256dh' => 'p', 'auth' => 'a'],
        ])->assertStatus(503);
    }

    public function test_a_device_moves_to_the_user_who_subscribed_it_last(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $this->subscribe($first);

        $this->subscribe($second);

        $this->assertSame(0, $first->pushSubscriptions()->count());
        $this->assertSame(1, $second->pushSubscriptions()->count());
    }

    public function test_logging_out_turns_off_that_browsers_notifications(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('push-subscriptions.store'), [
            'endpoint' => self::ENDPOINT,
            'keys' => ['p256dh' => 'p256dh-key', 'auth' => 'auth-token'],
        ])->assertOk();

        $this->subscribe($user, 'https://fcm.googleapis.com/fcm/send/other-device');

        $this->post(route('logout'));

        // Only the browser that logged out is removed; the user's other devices keep theirs.
        $this->assertSame(
            ['https://fcm.googleapis.com/fcm/send/other-device'],
            $user->pushSubscriptions()->pluck('endpoint')->all(),
        );
    }

    public function test_pages_turn_notifications_back_on_after_logging_in_again(): void
    {
        $user = User::factory()->create(['role' => 'end_user']);

        // Logged in, this session has no subscription yet: pages carry the script that restores a remembered choice.
        $this->actingAs($user)->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('mangrovemap.push.wanted', false)
            ->assertSee('device-push-restored', false)
            ->assertSee('data-user-id="'.$user->id.'"', false);

        // Once this browser is subscribed in this session, the pages no longer carry it.
        $this->postJson(route('push-subscriptions.store'), [
            'endpoint' => self::ENDPOINT,
            'keys' => ['p256dh' => 'p256dh-key', 'auth' => 'auth-token'],
        ])->assertOk();
        $this->get(route('dashboard'))->assertOk()->assertDontSee('pushManager.subscribe({ userVisibleOnly', false);

        // Without push keys on the server there is nothing to restore.
        config(['webpush.vapid.public_key' => null]);
        $this->withSession([])->flushSession();
        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertDontSee('mangrovemap.push.wanted', false);
    }

    public function test_notifications_push_only_to_users_who_turned_it_on(): void
    {
        $resident = User::factory()->create(['role' => 'end_user']);
        $expert = User::factory()->create(['role' => 'expert']);
        $delineation = Delineation::create(['user_id' => $resident->id, 'name' => 'Mangrove A', 'features' => []]);

        $this->assertNotContains('webpush', (new DelineationApproved($delineation))->via($resident));
        $this->assertNotContains('webpush', (new DelineationSubmittedForReview($delineation))->via($expert));

        $this->subscribe($resident);
        $this->subscribe($expert, 'https://fcm.googleapis.com/fcm/send/expert');

        $this->assertContains('webpush', (new DelineationApproved($delineation))->via($resident));
        $this->assertContains('webpush', (new DelineationSubmittedForReview($delineation))->via($expert));
        $this->assertContains('webpush', (new AdminAlert('New feedback', 'Message', 'feedback'))->via($expert));

        config(['webpush.vapid.private_key' => null]);
        $this->assertNotContains('webpush', (new DelineationApproved($delineation))->via($resident));
    }

    public function test_push_message_uses_the_bell_title_message_and_link(): void
    {
        $resident = User::factory()->create(['role' => 'end_user']);
        $expert = User::factory()->create(['role' => 'expert']);
        $delineation = Delineation::create(['user_id' => $resident->id, 'name' => 'Mangrove A', 'features' => []]);

        $approved = (new DelineationApproved($delineation))->toArray($resident);
        $message = DevicePushChannel::messageFromArray($approved, $resident, 'abc')->toArray();

        $this->assertSame('Delineation approved', $message['title']);
        $this->assertStringContainsString('Mangrove A', $message['body']);
        $this->assertSame(route('delineate', ['delineation' => $delineation->id], false), $message['data']['url']);

        $submitted = (new DelineationSubmittedForReview($delineation))->toArray($expert);
        $this->assertSame(
            route('expert.delineate', ['delineation' => $delineation->id], false),
            DevicePushChannel::targetUrl($submitted, $expert),
        );

        // Absolute links become paths on this site; notifications without a link open the bell page.
        $removed = (new DelineationRemoved('Old area', 'approved'))->toArray($resident);
        $this->assertSame(route('delineate', [], false), DevicePushChannel::targetUrl($removed, $resident));
        $this->assertSame(route('notifications.index', [], false), DevicePushChannel::targetUrl(['type' => 'x'], $resident));
    }

    public function test_a_failed_push_never_breaks_the_notification(): void
    {
        $resident = User::factory()->create(['role' => 'end_user']);
        $delineation = Delineation::create(['user_id' => $resident->id, 'name' => 'Mangrove A', 'features' => []]);
        $this->subscribe($resident);

        // The test keys are not real VAPID keys, so sending fails inside the channel.
        $resident->notifyNow(new DelineationApproved($delineation));

        $this->assertSame(1, $resident->notifications()->count());
    }

    public function test_notifications_page_shows_the_device_notifications_card_for_every_role(): void
    {
        foreach (['end_user', 'expert', 'admin'] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)->get(route('notifications.index'))
                ->assertOk()
                ->assertSee('id="device-notifications"', false)
                ->assertSee('data-configured="1"', false)
                ->assertSee('data-public-key="test-public-key"', false);
        }
    }

    public function test_profile_no_longer_has_the_device_notifications_section(): void
    {
        $user = User::factory()->create(['role' => 'end_user']);

        $this->actingAs($user)->get(route('profile.show'))
            ->assertOk()
            ->assertDontSee('id="device-notifications"', false);
    }
}
