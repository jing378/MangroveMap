<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MapQuickGuideTest extends TestCase
{
    use RefreshDatabase;

    public function test_resident_map_and_delineate_pages_have_their_quick_guide(): void
    {
        $resident = User::factory()->create(['role' => 'end_user']);

        $this->actingAs($resident)->get(route('map'))
            ->assertOk()
            ->assertSee('id="mapGuideBtn"', false)
            ->assertSee('Quick guide: Using the map')
            ->assertSee(route('guide').'#map', false);

        $this->actingAs($resident)->get(route('delineate'))
            ->assertOk()
            ->assertSee('Quick guide: Delineating')
            ->assertSee('An expert reviews it.')
            ->assertSee(route('guide').'#delineate', false);
    }

    public function test_expert_pages_get_the_expert_steps(): void
    {
        $expert = User::factory()->create(['role' => 'expert']);

        $this->actingAs($expert)->get(route('expert.delineate'))
            ->assertOk()
            ->assertSee('Quick guide: Delineating')
            ->assertSee('approved right away')
            ->assertDontSee('An expert reviews it.')
            ->assertSee(route('guide').'#review', false);

        $this->actingAs($expert)->get(route('expert.recommendations.index'))
            ->assertOk()
            ->assertSee('id="mapGuideBtn"', false)
            ->assertSee('Quick guide: Planting recommendations')
            ->assertSee(route('guide').'#recommendations', false);
    }
}
