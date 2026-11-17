<?php

namespace Tests\Feature;

use App\Models\Delineation;
use App\Models\User;
use App\Notifications\DelineationApproved;
use App\Notifications\DelineationSubmittedForReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LiveNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_poll_returns_unread_count_and_notifications_created_since_the_last_check(): void
    {
        Carbon::setTestNow('2026-11-17 10:00:00');
        $resident = User::factory()->create(['role' => 'end_user']);
        $expert = User::factory()->create(['role' => 'expert']);
        $delineation = Delineation::create(['user_id' => $resident->id, 'name' => 'Mangrove A', 'features' => []]);

        // Already there when the page loaded.
        $expert->notifyNow(new DelineationSubmittedForReview($delineation));
        $pageLoadedAt = Carbon::now()->addSeconds(5)->toIso8601String();

        Carbon::setTestNow('2026-11-17 10:00:30');
        $expert->notifyNow(new DelineationSubmittedForReview($delineation, true));

        $response = $this->actingAs($expert)
            ->getJson(route('notifications.poll', ['since' => $pageLoadedAt]))
            ->assertOk()
            ->assertJsonPath('unread', 2)
            ->assertJsonCount(1, 'fresh')
            ->assertJsonPath('fresh.0.type', 'delineation_submitted')
            ->assertJsonPath('fresh.0.title', 'Delineation resubmitted for review')
            ->assertJsonPath('fresh.0.icon', 'bi-hourglass-split')
            ->assertJsonPath('fresh.0.url', route('expert.delineate', ['delineation' => $delineation->id], false));

        $this->assertNotEmpty($response->json('now'));
    }

    public function test_poll_without_since_only_returns_the_count(): void
    {
        $resident = User::factory()->create(['role' => 'end_user']);
        $delineation = Delineation::create(['user_id' => $resident->id, 'name' => 'Mangrove A', 'features' => []]);
        $resident->notifyNow(new DelineationApproved($delineation));

        $this->actingAs($resident)->getJson(route('notifications.poll'))
            ->assertOk()
            ->assertJsonPath('unread', 1)
            ->assertJsonCount(0, 'fresh');

        $this->actingAs($resident)->getJson(route('notifications.poll', ['since' => 'not-a-date']))
            ->assertOk()
            ->assertJsonCount(0, 'fresh');
    }

    public function test_poll_only_shows_the_users_own_notifications(): void
    {
        $resident = User::factory()->create(['role' => 'end_user']);
        $other = User::factory()->create(['role' => 'end_user']);
        $delineation = Delineation::create(['user_id' => $resident->id, 'name' => 'Mangrove A', 'features' => []]);
        $since = now()->subMinute()->toIso8601String();
        $resident->notifyNow(new DelineationApproved($delineation));

        $this->actingAs($other)->getJson(route('notifications.poll', ['since' => $since]))
            ->assertOk()
            ->assertJsonPath('unread', 0)
            ->assertJsonCount(0, 'fresh');
    }

    public function test_poll_needs_a_logged_in_user(): void
    {
        $this->getJson(route('notifications.poll'))->assertUnauthorized();
    }

    public function test_every_layout_loads_the_live_notifications_script(): void
    {
        foreach (['end_user' => 'dashboard', 'expert' => 'expert.dashboard', 'admin' => 'admin.dashboard'] as $role => $page) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)->get(route($page))
                ->assertOk()
                ->assertSee(json_encode(route('notifications.poll')), false);
        }
    }
}
