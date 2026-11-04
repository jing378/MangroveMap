<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DroneUploadNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sidebar_has_a_drone_upload_tab(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('profile.show'))
            ->assertOk()
            ->assertSee('Drone Upload')
            ->assertSee('href="'.route('drone-imagery.page').'"', false)
            ->assertDontSee('Upload Image');
    }

    public function test_drone_upload_tab_renders_the_upload_form_and_list(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('drone-imagery.page'))
            ->assertOk()
            ->assertSee('id="droneUploadForm"', false)
            ->assertSee('Your drone imagery')
            ->assertSee(str_replace('/', '\/', route('drone-imagery.store')), false)
            ->assertDontSee('View on map');
    }

    public function test_only_residents_get_the_expert_review_step(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'end_user']))->get(route('drone-imagery.page'))
            ->assertOk()
            ->assertSee('"needs_expert_review":true', false);

        $this->actingAs(User::factory()->create(['role' => 'expert']))->get(route('drone-imagery.page'))
            ->assertOk()
            ->assertSee('"needs_expert_review":false', false);
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get(route('drone-imagery.page'))->assertRedirect(route('login'));
    }

    public function test_photo_upload_pages_are_gone(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/upload_image')->assertNotFound();
        $this->actingAs($user)->get('/upload')->assertNotFound();
        $this->actingAs($user)->get('/classify')->assertNotFound();
    }
}
