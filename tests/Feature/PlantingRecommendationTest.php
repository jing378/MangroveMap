<?php

namespace Tests\Feature;

use App\Models\Delineation;
use App\Models\PlantingRecommendation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlantingRecommendationTest extends TestCase
{
    use RefreshDatabase;

    private const AREA = [[10.0, 124.0], [10.0, 124.01], [10.01, 124.01], [10.0, 124.0]];

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Mudflat east of the river mouth',
            'delineation_id' => null,
            'features' => [['type' => 'area', 'coords' => self::AREA]],
            'suitability' => 'highly_suitable',
            'recommended_genera' => ['Rhizophora', 'Avicennia'],
            'recommendation' => 'Plant Rhizophora propagules at 1 m spacing on the seaward edge.',
            'site_notes' => 'Soft mud, inundated at high tide.',
        ], $overrides);
    }

    private function approvedDelineation(User $owner, array $attributes = []): Delineation
    {
        return Delineation::create(array_merge([
            'user_id' => $owner->id,
            'name' => 'Approved mangrove stand',
            'notes' => 'Resident delineation',
            'features' => [['type' => 'area', 'coords' => self::AREA, 'genera' => []]],
            'is_approved' => true,
        ], $attributes));
    }

    public function test_expert_saves_a_drawn_area_recommendation_separately_from_delineations(): void
    {
        $expert = User::factory()->create(['role' => 'expert']);

        $response = $this->actingAs($expert)
            ->postJson(route('expert.recommendations.store'), $this->payload())
            ->assertCreated()
            ->assertJsonPath('recommendation.user_id', $expert->id)
            ->assertJsonPath('recommendation.expert_name', $expert->name)
            ->assertJsonPath('recommendation.can_edit', true)
            ->assertJsonPath('recommendation.suitability', 'highly_suitable')
            ->assertJsonPath('recommendation.recommended_genera', ['Rhizophora', 'Avicennia'])
            ->assertJsonStructure(['recommendation' => ['features' => [['location', 'location_status']]]]);
        $this->assertEquals(self::AREA, $response->json('recommendation.features.0.coords'));

        $this->assertSame(0, Delineation::count(), 'Recommendations must not create delineations.');
        $recommendation = PlantingRecommendation::sole();
        $this->assertSame($expert->id, (int) $recommendation->user_id);
        $this->assertNull($recommendation->delineation_id);
        $this->assertSame('Soft mud, inundated at high tide.', $recommendation->site_notes);
    }

    public function test_selected_area_links_an_approved_delineation_only(): void
    {
        $resident = User::factory()->create(['role' => 'end_user']);
        $expert = User::factory()->create(['role' => 'expert']);
        $approved = $this->approvedDelineation($resident);
        $pending = $this->approvedDelineation($resident, ['is_approved' => false]);

        $this->actingAs($expert)
            ->postJson(route('expert.recommendations.store'), $this->payload(['delineation_id' => $approved->id]))
            ->assertCreated()
            ->assertJsonPath('recommendation.delineation_id', $approved->id)
            ->assertJsonPath('recommendation.delineation_name', 'Approved mangrove stand');

        $this->actingAs($expert)
            ->postJson(route('expert.recommendations.store'), $this->payload(['delineation_id' => $pending->id]))
            ->assertJsonValidationErrors('delineation_id');

        $this->assertSame($approved->id, (int) PlantingRecommendation::sole()->delineation_id);
        $this->assertEquals($approved->fresh()->features, $approved->features, 'The source delineation is not modified.');
    }

    public function test_validation_of_genus_suitability_and_geometry(): void
    {
        $expert = User::factory()->create(['role' => 'expert']);
        $this->actingAs($expert);
        $url = route('expert.recommendations.store');

        $this->postJson($url, $this->payload(['recommended_genera' => []]))
            ->assertJsonValidationErrors('recommended_genera');
        $this->postJson($url, $this->payload(['recommended_genera' => ['Nypa']]))
            ->assertJsonValidationErrors('recommended_genera.0');
        $this->postJson($url, $this->payload(['suitability' => 'excellent']))
            ->assertJsonValidationErrors('suitability');
        $this->postJson($url, $this->payload(['features' => [['type' => 'area', 'coords' => [[10, 124], [10, 124], [10, 124.01]]]]]))
            ->assertJsonValidationErrors('features.0.coords');
        $this->postJson($url, $this->payload(['features' => [['type' => 'point', 'coords' => [10, 124]]]]))
            ->assertJsonValidationErrors('features.0.type');
        $this->assertSame(0, PlantingRecommendation::count());

        // "Not suitable" needs no genus, and any genus sent with it is dropped.
        $this->postJson($url, $this->payload(['suitability' => 'not_suitable', 'recommended_genera' => ['Sonneratia']]))
            ->assertCreated()
            ->assertJsonPath('recommendation.recommended_genera', []);
    }

    public function test_recommendation_text_is_optional(): void
    {
        $expert = User::factory()->create(['role' => 'expert']);

        $this->actingAs($expert)
            ->postJson(route('expert.recommendations.store'), $this->payload(['recommendation' => '']))
            ->assertCreated()
            ->assertJsonPath('recommendation.recommendation', null);

        $this->assertNull(PlantingRecommendation::sole()->recommendation);
    }

    public function test_owner_updates_area_and_details_in_place(): void
    {
        $expert = User::factory()->create(['role' => 'expert']);
        $this->actingAs($expert)->postJson(route('expert.recommendations.store'), $this->payload())->assertCreated();
        $recommendation = PlantingRecommendation::sole();

        $editedArea = [[10.0, 124.0], [10.0, 124.02], [10.005, 124.025], [10.012, 124.01], [10.0, 124.0]];
        $this->putJson(route('expert.recommendations.update', $recommendation), $this->payload([
            'features' => [['type' => 'area', 'coords' => $editedArea]],
            'suitability' => 'moderately_suitable',
            'recommended_genera' => ['Sonneratia'],
            'recommendation' => 'Plant Sonneratia on the sandy fringe only.',
            'site_notes' => '',
        ]))
            ->assertOk()
            ->assertJsonPath('recommendation.id', $recommendation->id)
            ->assertJsonCount(5, 'recommendation.features.0.coords');

        $this->assertSame(1, PlantingRecommendation::count());
        $fresh = $recommendation->fresh();
        $this->assertSame('moderately_suitable', $fresh->suitability);
        $this->assertSame(['Sonneratia'], $fresh->recommended_genera);
        $this->assertEquals([10.005, 124.025], $fresh->features[0]['coords'][2]);
        $this->assertNull($fresh->site_notes);
    }

    public function test_other_experts_can_view_but_not_change_a_recommendation(): void
    {
        $author = User::factory()->create(['role' => 'expert']);
        $other = User::factory()->create(['role' => 'expert']);
        $recommendation = $author->plantingRecommendations()->create([
            'name' => 'Author area',
            'features' => [['type' => 'area', 'coords' => self::AREA]],
            'suitability' => 'highly_suitable',
            'recommended_genera' => ['Avicennia'],
            'recommendation' => 'Plant Avicennia.',
        ]);

        $this->actingAs($other)
            ->putJson(route('expert.recommendations.update', $recommendation), $this->payload())
            ->assertForbidden();
        $this->actingAs($other)
            ->deleteJson(route('expert.recommendations.destroy', $recommendation))
            ->assertForbidden();
        $this->assertSame('Author area', $recommendation->fresh()->name);

        $page = $this->actingAs($other)->get(route('expert.recommendations.index'))->assertOk();
        $listed = collect($page->viewData('recommendations'))->firstWhere('id', $recommendation->id);
        $this->assertSame('Author area', $listed['name']);
        $this->assertFalse($listed['can_edit']);

        $this->actingAs($author)
            ->deleteJson(route('expert.recommendations.destroy', $recommendation))
            ->assertOk();
        $this->assertSame(0, PlantingRecommendation::count());
    }

    public function test_expert_page_lists_approved_areas_and_sidebar_tab_but_residents_are_redirected(): void
    {
        $resident = User::factory()->create(['role' => 'end_user']);
        $expert = User::factory()->create(['role' => 'expert']);
        $approved = $this->approvedDelineation($resident);
        $this->approvedDelineation($resident, ['is_approved' => false, 'name' => 'Pending stand']);

        $response = $this->actingAs($expert)->get(route('expert.recommendations.index'))
            ->assertOk()
            ->assertSee(route('expert.recommendations.index'), false)
            ->assertSee('<span>Planting Recommendation</span>', false)
            ->assertDontSee('<div class="sec">Summary</div>', false)
            ->assertDontSee('id="recommendationList"', false)
            ->assertSee('rec-location-marker', false)
            ->assertSee('bi-geo-alt-fill', false)
            ->assertSee('id="recommendationForm"', false);
        $this->assertSame([$approved->id], collect($response->viewData('referenceAreas'))->pluck('id')->all());

        $this->actingAs($resident)->get(route('expert.recommendations.index'))->assertRedirect(route('dashboard'));
        $this->actingAs($resident)
            ->postJson(route('expert.recommendations.store'), $this->payload())
            ->assertRedirect(route('dashboard'));
        $this->assertSame(0, PlantingRecommendation::count());
    }

    public function test_deleting_the_source_delineation_keeps_the_recommendation(): void
    {
        $resident = User::factory()->create(['role' => 'end_user']);
        $expert = User::factory()->create(['role' => 'expert']);
        $delineation = $this->approvedDelineation($resident);
        $this->actingAs($expert)
            ->postJson(route('expert.recommendations.store'), $this->payload(['delineation_id' => $delineation->id]))
            ->assertCreated();

        $delineation->delete();

        $recommendation = PlantingRecommendation::sole();
        $this->assertNull($recommendation->delineation_id);
        $this->assertEquals(self::AREA, $recommendation->features[0]['coords']);
    }
}
