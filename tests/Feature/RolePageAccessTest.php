<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePageAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_are_sent_back_from_the_resident_tool_pages(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        foreach (['delineate', 'drone-imagery.page', 'tile-explorer.page'] as $route) {
            $this->actingAs($admin)->get(route($route))
                ->assertRedirect(route('admin.dashboard'))
                ->assertSessionHas('error');
        }

        $this->actingAs($admin)->get(route('drone-imagery.edit', ['layerId' => 1]))
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_admins_keep_the_shared_map_with_admin_links_in_the_sidebar(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('map'))
            ->assertOk()
            ->assertSee(route('admin.dashboard'), false)
            ->assertSee(route('admin.map-layers.index'), false)
            ->assertDontSee('<span>Tile Explorer</span>', false)
            ->assertDontSee('<span>Drone Upload</span>', false)
            ->assertDontSee('<span>Delineate</span>', false);
    }

    public function test_experts_are_sent_to_their_own_map_and_delineate_pages(): void
    {
        $expert = User::factory()->create(['role' => 'expert']);

        $this->actingAs($expert)->get(route('delineate', ['delineation' => 5]))
            ->assertRedirect(route('expert.delineate', ['delineation' => 5]));
        $this->actingAs($expert)->get(route('map', ['compare' => 'previous']))
            ->assertRedirect(route('expert.map', ['compare' => 'previous']));
        $this->actingAs($expert)->get(route('expert.delineate'))->assertOk();
        $this->actingAs($expert)->get(route('expert.map'))->assertOk();
    }

    public function test_residents_still_use_all_their_pages(): void
    {
        $resident = User::factory()->create(['role' => 'end_user']);

        foreach (['map', 'delineate', 'drone-imagery.page', 'tile-explorer.page'] as $route) {
            $this->actingAs($resident)->get(route($route))->assertOk();
        }
    }
}
