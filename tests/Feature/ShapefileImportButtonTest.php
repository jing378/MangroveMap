<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShapefileImportButtonTest extends TestCase
{
    use RefreshDatabase;

    public function test_delineate_pages_offer_shapefile_import(): void
    {
        $resident = User::factory()->create(['role' => 'end_user']);
        $expert = User::factory()->create(['role' => 'expert']);

        $this->actingAs($resident)->get(route('delineate'))
            ->assertOk()
            ->assertSee('id="importShapefileBtn"', false)
            ->assertSee('id="importShapefileInput"', false);

        $this->actingAs($expert)->get(route('expert.delineate'))
            ->assertOk()
            ->assertSee('id="importShapefileBtn"', false);
    }

    public function test_map_page_has_no_shapefile_import(): void
    {
        $resident = User::factory()->create(['role' => 'end_user']);

        $this->actingAs($resident)->get(route('map'))
            ->assertOk()
            ->assertDontSee('id="importShapefileBtn"', false);
    }
}
