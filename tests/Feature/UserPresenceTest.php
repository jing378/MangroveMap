<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Login status of each account, shown to admins on the Users page. */
class UserPresenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_using_the_site_today_is_recorded_as_last_seen(): void
    {
        $resident = User::factory()->create(['role' => 'end_user']);
        $this->assertFalse($resident->wasSeenToday());
        $this->assertSame('Never logged in', $resident->lastSeenLabel());

        // Kept signed in ("remember me"): no new login, but visiting a page counts.
        $this->actingAs($resident)->get(route('guide'))->assertOk();

        $resident->refresh();
        $this->assertNotNull($resident->last_seen_at);
        $this->assertTrue($resident->wasSeenToday());
        $this->assertStringStartsWith('Last seen today, ', $resident->lastSeenLabel());
        $this->assertSame(1, User::seenToday()->count());
    }

    public function test_a_login_today_counts_and_an_older_one_does_not(): void
    {
        $today = User::factory()->create(['last_login_at' => now()]);
        $earlier = User::factory()->create(['last_login_at' => now()->subDays(3)]);

        $this->assertTrue($today->wasSeenToday());
        $this->assertFalse($earlier->wasSeenToday());
        $this->assertSame('Last seen 3 days ago', $earlier->lastSeenLabel());
        $this->assertSame([$today->id], User::seenToday()->pluck('id')->all());
    }

    /** A live session row, as the database session driver stores one for a signed-in user. */
    private function liveSession(User $user, \Carbon\CarbonInterface $lastActivity): void
    {
        \Illuminate\Support\Facades\DB::table('sessions')->insert([
            'id' => \Illuminate\Support\Str::random(40),
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => '',
            'last_activity' => $lastActivity->getTimestamp(),
        ]);
    }

    public function test_admin_users_page_shows_who_is_logged_in_right_now(): void
    {
        config(['session.driver' => 'database', 'session.lifetime' => 120]);
        $admin = User::factory()->create(['role' => 'admin']);
        $now = User::factory()->create(['role' => 'end_user', 'last_login_at' => now()]);
        $left = User::factory()->create(['role' => 'end_user', 'last_login_at' => now()]);
        $expired = User::factory()->create(['role' => 'end_user', 'last_login_at' => now()->subWeek()]);

        $this->liveSession($now, now()->subMinutes(5));
        $this->liveSession($expired, now()->subHours(5)); // past the session lifetime

        $this->assertSame([$now->id], User::loggedInNowIds());

        $response = $this->actingAs($admin)->get(route('admin.users'))->assertOk();
        $response->assertViewHas('loggedInNowCount', 1)
            ->assertSee('Active Now')
            ->assertSee('Active · logged in ')
            // Logged in earlier today but no live session (logged out): "Last seen", never "Active".
            ->assertSee('Last seen today, ')
            ->assertSee('Last seen 1 week ago');
        $this->assertStringStartsWith('Active · logged in ', $now->activeNowLabel());
        $this->assertTrue($left->wasSeenToday());
    }

    public function test_no_one_counts_as_logged_in_without_database_sessions(): void
    {
        config(['session.driver' => 'array']);
        $this->liveSession(User::factory()->create(), now());

        $this->assertSame([], User::loggedInNowIds());
    }
}
