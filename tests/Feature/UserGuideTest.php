<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserGuideTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/guide')->assertRedirect('/login');
    }

    public function test_resident_sees_resident_sections_only(): void
    {
        $resident = User::factory()->create(['role' => 'end_user']);

        $this->actingAs($resident)->get('/guide')
            ->assertOk()
            ->assertSee('id="delineate"', false)
            ->assertSee('id="drone"', false)
            ->assertDontSee('id="review"', false)
            ->assertDontSee('id="admin"', false);
    }

    public function test_expert_sees_expert_sections(): void
    {
        $expert = User::factory()->create(['role' => 'expert']);

        $this->actingAs($expert)->get('/guide')
            ->assertOk()
            ->assertSee('id="review"', false)
            ->assertSee('id="recommendations"', false)
            ->assertDontSee('id="delineate"', false)
            ->assertDontSee('id="admin"', false);
    }

    public function test_admin_sees_every_section(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/guide')
            ->assertOk()
            ->assertSee('id="delineate"', false)
            ->assertSee('id="review"', false)
            ->assertSee('id="admin"', false);
    }
}
