<?php

namespace Tests\Feature;

use App\Models\Delineation;
use App\Models\PlantingRecommendation;
use App\Models\User;
use App\Services\DelineationObservationRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomePageStatsTest extends TestCase
{
    use RefreshDatabase;

    // About 1.1 km x 1.1 km: roughly 121 ha.
    private const AREA = [
        'type' => 'area',
        'coords' => [[10.35, 124.97], [10.35, 124.98], [10.36, 124.98], [10.36, 124.97]],
        'genera' => ['Rhizophora', 'Avicennia'],
        'location' => 'San Isidro, Maasin City, Southern Leyte',
        'location_details' => [
            'barangay' => ['name' => 'San Isidro'],
            'municipality' => ['name' => 'Maasin City'],
        ],
    ];

    private function approved(User $user, array $features, array $attributes = []): Delineation
    {
        return Delineation::create(array_merge([
            'user_id' => $user->id,
            'name' => 'Approved zone',
            'features' => $features,
            'is_approved' => true,
            'is_rejected' => false,
            'approved_at' => now(),
        ], $attributes));
    }

    public function test_stats_use_hectares_from_approved_delineations_only(): void
    {
        $alice = User::factory()->create(['role' => 'end_user']);
        $bob = User::factory()->create(['role' => 'end_user']);
        $this->approved($alice, [self::AREA]);
        $this->approved($bob, [array_merge(self::AREA, ['genera' => ['Sonneratia']])]);
        // Pending and rejected records must not count.
        $this->approved($bob, [self::AREA], ['is_approved' => false, 'approved_at' => null]);
        $this->approved($bob, [self::AREA], ['is_approved' => false, 'is_rejected' => true, 'approved_at' => null]);

        $expectedHa = round(2 * DelineationObservationRecorder::areaHectares([self::AREA]), 2);

        $response = $this->get('/')->assertOk()
            ->assertViewHas('mappedAreaHa', $expectedHa)
            ->assertViewHas('barangayCount', 1)
            ->assertViewHas('generaCount', 3)
            ->assertViewHas('contributorCount', 2)
            ->assertViewHas('delineations', fn($records) => count($records) === 2);

        $response->assertSee(number_format($expectedHa, 2) . '<span>ha</span>', false)
            ->assertDontSee('km²');
    }

    public function test_public_payload_has_area_genera_and_trend_but_no_private_fields(): void
    {
        $owner = User::factory()->create(['role' => 'end_user', 'email' => 'owner@example.com']);
        $delineation = $this->approved($owner, [self::AREA]);
        $delineation->annualObservations()->create([
            'year' => 2025,
            'observation_date' => '2025-03-01',
            'coverage_area_ha' => 118.5,
            'source' => 'Field survey',
        ]);

        $record = $this->get('/')->assertOk()->viewData('delineations')->first();

        $this->assertSame($owner->name, $record['user']['name']);
        $this->assertSame(['Rhizophora', 'Avicennia'], $record['genera']->all());
        $this->assertSame('San Isidro, Maasin City, Southern Leyte', $record['features'][0]['location']);
        $this->assertGreaterThan(100, $record['features'][0]['area_ha']);
        $this->assertSame([['year' => 2025, 'area_ha' => 118.5]], $record['coverage_trend']->all());
        $this->assertArrayNotHasKey('location_details', $record['features'][0]);
        $this->assertStringNotContainsString('owner@example.com', json_encode($record));
    }

    public function test_public_map_shows_visible_planting_recommendations_without_site_notes(): void
    {
        $expert = User::factory()->create(['role' => 'expert']);
        $resident = User::factory()->create(['role' => 'end_user']);
        $approved = $this->approved($resident, [self::AREA]);
        $pending = $this->approved($resident, [self::AREA], ['is_approved' => false, 'approved_at' => null]);

        $recommend = fn(array $attributes) => PlantingRecommendation::create(array_merge([
            'user_id' => $expert->id,
            'features' => [['type' => 'area', 'coords' => self::AREA['coords']]],
            'suitability' => 'highly_suitable',
            'recommended_genera' => ['Rhizophora'],
            'recommendation' => 'Plant propagules at 1 m spacing before the rainy season.',
            'site_notes' => 'Private working note',
        ], $attributes));
        $recommend(['name' => 'On approved area', 'delineation_id' => $approved->id]);
        $recommend(['name' => 'Freely drawn site', 'delineation_id' => null, 'suitability' => 'not_suitable', 'recommended_genera' => []]);
        $recommend(['name' => 'On pending area', 'delineation_id' => $pending->id]);

        $response = $this->get('/')->assertOk();
        $recommendations = $response->viewData('plantingRecommendations');

        $this->assertEqualsCanonicalizing(['On approved area', 'Freely drawn site'], $recommendations->pluck('name')->all());
        $onApproved = $recommendations->firstWhere('name', 'On approved area');
        $this->assertSame($approved->id, $onApproved['delineation_id']);
        $this->assertSame('Highly suitable', $onApproved['suitability_label']);
        $this->assertSame($expert->name, $onApproved['expert_name']);
        $this->assertGreaterThan(100, $onApproved['area_ha']);
        $this->assertArrayNotHasKey('site_notes', $onApproved);

        $response->assertDontSee('Private working note')
            ->assertSee('id="toggleRecommendations"', false)
            ->assertSee('bi-geo-alt-fill', false);
    }

    public function test_empty_system_shows_zeros_not_sample_numbers(): void
    {
        $this->get('/')->assertOk()
            ->assertViewHas('mappedAreaHa', 0)
            ->assertSee('0.00<span>ha</span>', false)
            ->assertDontSee('46,382');
    }
}
