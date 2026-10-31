<?php

namespace Tests\Feature;

use App\Models\Delineation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ExpertDelineationReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_approval_without_a_planting_recommendation_preserves_existing_text(): void
    {
        Notification::fake();
        $resident = User::factory()->create(['role' => 'end_user']);
        $expert = User::factory()->create(['role' => 'expert']);
        $delineation = Delineation::create([
            'user_id' => $resident->id,
            'name' => 'Coastal area',
            'notes' => 'Mangrove stand',
            'features' => [['type' => 'area', 'coords' => [[10, 124], [10, 124.01], [10.01, 124]]]],
            'planting_recommendation' => 'Existing recommendation',
        ]);

        $this->actingAs($expert)
            ->postJson(route('expert.delineations.approve', $delineation))
            ->assertOk();

        $this->assertSame('Existing recommendation', $delineation->fresh()->planting_recommendation);
    }
}
