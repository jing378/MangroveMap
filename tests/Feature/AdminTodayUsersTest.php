<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** Admin dashboard "Today": who signed up or logged in during the local (Asia/Manila) day. */
class AdminTodayUsersTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_lists_todays_sign_ups_and_logins_by_the_local_day(): void
    {
        // 02:00 in Manila on Nov 16 is still Nov 15 in UTC: "today" must follow Manila.
        $this->travelTo(Carbon::parse('2026-11-16 02:00', 'Asia/Manila')->utc());
        $yesterday = now()->subHours(5); // 21:00 Manila, Nov 15

        $admin = User::factory()->create(['role' => 'admin', 'created_at' => $yesterday->copy()->subMonth()]);
        $new = User::factory()->unverified()->create(['name' => 'Nina New', 'created_at' => now()->subHour()]);
        $returning = User::factory()->create([
            'name' => 'Rey Returning', 'created_at' => $yesterday->copy()->subMonth(), 'last_login_at' => now()->subMinutes(30),
        ]);
        $quiet = User::factory()->create([
            'name' => 'Quiet Quinn', 'created_at' => $yesterday, 'last_login_at' => $yesterday,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();

        $response->assertViewHas('newUsersToday', 1)->assertViewHas('loggedInToday', 1);
        $this->assertSame([$new->id, $returning->id], $response->viewData('todayUsers')->pluck('id')->all());
        $response->assertSeeInOrder(['New sign-ups today', 'Nina New', 'New sign-up', 'Not verified', 'Rey Returning', 'Logged in'])
            ->assertSee(route('admin.users.show', $new), false)
            ->assertDontSee('Quiet Quinn');
    }

    public function test_dashboard_says_so_when_nobody_came_today(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'created_at' => now()->subWeek()]);

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('No sign-ups or logins yet today.');
    }
}
