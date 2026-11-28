<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingTourTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_opens_by_itself_until_the_user_finishes_or_skips_it(): void
    {
        $resident = User::factory()->create(['role' => 'end_user', 'onboarded_at' => null]);

        $this->actingAs($resident)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('id="onboardingDialog"', false)
            ->assertSee('data-auto-open="1"', false);

        $this->actingAs($resident)->postJson(route('onboarding.complete'))->assertOk();
        $this->assertNotNull($resident->fresh()->onboarded_at);

        // Still on the page (Help & Guide can replay it), but no longer opens by itself.
        $this->actingAs($resident->fresh())->get(route('dashboard'))
            ->assertOk()
            ->assertSee('id="onboardingDialog"', false)
            ->assertSee('data-auto-open="0"', false);
    }

    public function test_completing_twice_keeps_the_first_time(): void
    {
        $resident = User::factory()->create(['role' => 'end_user', 'onboarded_at' => now()->subDay()]);
        $first = $resident->onboarded_at->toIso8601String();

        $this->actingAs($resident)->postJson(route('onboarding.complete'))
            ->assertOk()
            ->assertJson(['onboarded_at' => $first]);
    }

    public function test_each_role_gets_its_own_steps(): void
    {
        $resident = User::factory()->create(['role' => 'end_user']);
        $this->actingAs($resident)->get(route('dashboard'))
            ->assertSee('Draw a mangrove area')
            ->assertSee('An expert reviews it')
            ->assertDontSee('Approve expert accounts');

        $expert = User::factory()->create(['role' => 'expert']);
        $this->actingAs($expert)->get(route('expert.dashboard'))
            ->assertSee('Submissions come to you')
            ->assertSee('Approve or reject')
            ->assertDontSee('Draw a mangrove area');

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertSee('id="onboardingDialog"', false)
            ->assertSee('Approve expert accounts')
            ->assertSee('Look after the AI models')
            // No "Go to" links into pages admins can't open.
            ->assertDontSee('Go to Delineate')
            ->assertDontSee('Go to Drone Upload');
    }

    public function test_welcome_step_shows_the_logo_and_links_can_be_resumed(): void
    {
        $resident = User::factory()->create(['role' => 'end_user']);

        $this->actingAs($resident)->get(route('dashboard'))
            ->assertSee('class="onboarding-icon is-logo"', false)
            ->assertSee('src="/icon-192.png"', false)
            // "Go to ..." links leave the tour open to continue at the same step (the resume button).
            ->assertSee('id="onboardingResume"', false)
            ->assertSee('data-onboarding-goto', false)
            ->assertSee('data-user="'.$resident->id.'"', false);
    }

    public function test_guide_has_a_replay_button(): void
    {
        $resident = User::factory()->create(['role' => 'end_user', 'onboarded_at' => now()]);

        $this->actingAs($resident)->get(route('guide'))
            ->assertOk()
            ->assertSee('data-onboarding-open', false)
            ->assertSee('Show the welcome tour');
    }

    public function test_guests_cannot_complete_it(): void
    {
        $this->postJson(route('onboarding.complete'))->assertUnauthorized();
    }
}
