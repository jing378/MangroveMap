<?php

namespace Tests\Feature;

use App\Http\Controllers\EndUserController;
use App\Models\Delineation;
use App\Models\PlantingRecommendation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DelineationPlantingRecommendationDisplayTest extends TestCase
{
    use RefreshDatabase;

    private const AREA = [[10.0, 124.0], [10.0, 124.01], [10.01, 124.01], [10.0, 124.0]];

    protected function setUp(): void
    {
        parent::setUp();

        // buildDashboardData uses MySQL's YEAR(); provide it for the in-memory SQLite test DB.
        DB::connection()->getPdo()
            ->sqliteCreateFunction('YEAR', fn($date) => $date ? (int) substr($date, 0, 4) : null, 1);
    }

    private function delineation(User $owner, array $attributes = []): Delineation
    {
        return Delineation::create(array_merge([
            'user_id' => $owner->id,
            'name' => 'Riverside stand',
            'notes' => 'Dense stand',
            'features' => [['type' => 'area', 'coords' => self::AREA, 'genera' => ['Avicennia']]],
        ], $attributes));
    }

    private function recommend(User $expert, Delineation $delineation, array $attributes = []): PlantingRecommendation
    {
        return $expert->plantingRecommendations()->create(array_merge([
            'delineation_id' => $delineation->id,
            'name' => 'Mudflat evaluation',
            'features' => [['type' => 'area', 'coords' => self::AREA]],
            'suitability' => 'highly_suitable',
            'recommended_genera' => ['Rhizophora', 'Sonneratia'],
            'recommendation' => 'Plant propagules at 1 m spacing.',
        ], $attributes));
    }

    private function mapRecord(User $viewer, Delineation $delineation): array
    {
        $this->actingAs($viewer);
        $data = app(EndUserController::class)->buildDashboardData();

        return collect($data['delineations'])
            ->concat($data['approvedDelineationsForMap'])
            ->concat($data['residentDelineationsForMap'])
            ->firstWhere('id', $delineation->id);
    }

    public function test_resident_sees_recommended_genus_on_approved_community_delineation(): void
    {
        $owner = User::factory()->create(['role' => 'end_user']);
        $viewer = User::factory()->create(['role' => 'end_user']);
        $expert = User::factory()->create(['role' => 'expert', 'name' => 'Expert Juan']);
        $delineation = $this->delineation($owner, ['is_approved' => true, 'approved_at' => now()]);
        $this->recommend($expert, $delineation);

        $entries = $this->mapRecord($viewer, $delineation)['planting_recommendations'];

        $this->assertCount(1, $entries);
        $this->assertSame(['Rhizophora', 'Sonneratia'], $entries[0]['recommended_genera']);
        $this->assertSame('Highly suitable', $entries[0]['suitability_label']);
        $this->assertSame('Expert Juan', $entries[0]['expert_name']);
        $this->assertSame('Plant propagules at 1 m spacing.', $entries[0]['recommendation']);
    }

    public function test_owner_sees_recommended_genus_on_own_approved_delineation(): void
    {
        $owner = User::factory()->create(['role' => 'end_user']);
        $expert = User::factory()->create(['role' => 'expert']);
        $delineation = $this->delineation($owner, ['is_approved' => true, 'approved_at' => now()]);
        $this->recommend($expert, $delineation, ['suitability' => 'not_suitable', 'recommended_genera' => []]);

        $entries = $this->mapRecord($owner, $delineation)['planting_recommendations'];

        $this->assertSame('not_suitable', $entries[0]['suitability']);
        $this->assertSame([], $entries[0]['recommended_genera']);
    }

    public function test_resident_does_not_get_recommendations_for_unapproved_delineation(): void
    {
        $owner = User::factory()->create(['role' => 'end_user']);
        $expert = User::factory()->create(['role' => 'expert']);
        $delineation = $this->delineation($owner, ['is_approved' => true, 'approved_at' => now()]);
        $this->recommend($expert, $delineation);
        // e.g. approval later withdrawn: the resident panel hides the row, so send nothing.
        $delineation->update(['is_approved' => false]);

        $this->assertSame([], $this->mapRecord($owner, $delineation)['planting_recommendations']);
    }

    public function test_expert_sees_recommendations_newest_first(): void
    {
        $owner = User::factory()->create(['role' => 'end_user']);
        $expert = User::factory()->create(['role' => 'expert']);
        $delineation = $this->delineation($owner, ['is_approved' => true, 'approved_at' => now()]);
        $older = $this->recommend($expert, $delineation, ['recommended_genera' => ['Avicennia']]);
        $older->forceFill(['created_at' => now()->subDay()])->save();
        $this->recommend($expert, $delineation, ['recommended_genera' => ['Rhizophora']]);

        $entries = $this->mapRecord($expert, $delineation)['planting_recommendations'];

        $this->assertSame([['Rhizophora'], ['Avicennia']], array_column($entries, 'recommended_genera'));
    }

    public function test_map_page_shows_recommendation_areas_layer_to_residents(): void
    {
        $owner = User::factory()->create(['role' => 'end_user']);
        $viewer = User::factory()->create(['role' => 'end_user']);
        $expert = User::factory()->create(['role' => 'expert']);
        $approved = $this->delineation($owner, ['is_approved' => true, 'approved_at' => now()]);
        $withdrawn = $this->delineation($owner, ['is_approved' => true, 'approved_at' => now()]);
        $onApproved = $this->recommend($expert, $approved, ['name' => 'On approved']);
        $this->recommend($expert, $withdrawn, ['name' => 'On withdrawn']);
        $withdrawn->update(['is_approved' => false]);
        $drawn = $this->recommend($expert, $approved, ['name' => 'Freely drawn', 'delineation_id' => null]);

        $response = $this->actingAs($viewer)->get(route('map'))->assertOk();

        $this->assertEqualsCanonicalizing(
            [$onApproved->id, $drawn->id],
            $response->viewData('plantingRecommendationsForMap')->pluck('id')->all()
        );
        $response->assertSee('id="plantingRecommendationToggle"', false)
            ->assertSee('renderPlantingRecommendations', false)
            ->assertDontSee('On withdrawn');

        // Experts see every recommendation area, including the withdrawn delineation's.
        $this->assertCount(3, $this->actingAs($expert)->get(route('expert.map'))
            ->assertOk()
            ->viewData('plantingRecommendationsForMap'));
    }

    public function test_delineate_page_has_no_recommendation_layer_toggle(): void
    {
        $resident = User::factory()->create(['role' => 'end_user']);

        $this->actingAs($resident)
            ->get(route('delineate'))
            ->assertOk()
            ->assertDontSee('id="plantingRecommendationToggle"', false);
    }

    public function test_map_page_embeds_recommended_genus_for_the_details_panel(): void
    {
        $owner = User::factory()->create(['role' => 'end_user']);
        $expert = User::factory()->create(['role' => 'expert']);
        $delineation = $this->delineation($owner, ['is_approved' => true, 'approved_at' => now()]);
        $this->recommend($expert, $delineation);

        $this->actingAs($owner)
            ->get(route('map'))
            ->assertOk()
            ->assertSee('renderDelineationPlantingRecommendation', false)
            ->assertSee('"recommended_genera":["Rhizophora","Sonneratia"]', false);
    }
}
