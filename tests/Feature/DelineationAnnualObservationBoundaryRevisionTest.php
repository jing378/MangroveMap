<?php

namespace Tests\Feature;

use App\Models\Delineation;
use App\Models\DelineationAnnualObservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DelineationAnnualObservationBoundaryRevisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_expert_saves_a_new_boundary_revision_without_changing_the_original_delineation(): void
    {
        $expert = User::factory()->create(['role' => 'expert']);
        $baseline = [
            ['type' => 'area', 'coords' => [[10.0, 124.0], [10.0, 124.01], [10.01, 124.01]]],
        ];
        $adjusted = [
            ['type' => 'area', 'coords' => [[10.0, 124.0], [10.0, 124.02], [10.01, 124.01]]],
        ];
        $delineation = Delineation::create([
            'user_id' => $expert->id,
            'name' => 'North Shore',
            'notes' => 'Baseline zone',
            'features' => $baseline,
        ]);

        $response = $this->actingAs($expert)->post(route('expert.annual-observations.store'), [
            'delineation_id' => $delineation->id,
            'year' => now()->year,
            'observation_date' => now()->toDateString(),
            'coverage_area_ha' => 12.5,
            'source' => 'Field survey',
            'features' => $adjusted,
        ]);

        $response->assertRedirect(route('expert.annual-observations.create'));
        $observation = DelineationAnnualObservation::firstOrFail();
        $this->assertDatabaseHas('delineation_annual_observation_boundary_revisions', [
            'annual_observation_id' => $observation->id,
            'version' => 1,
            'created_by' => $expert->id,
        ]);
        $this->assertSame($baseline, $delineation->fresh()->features);
        $this->assertSame($adjusted, $observation->boundaryRevisions()->firstOrFail()->features);
    }

    public function test_saving_the_same_boundary_does_not_create_a_revision(): void
    {
        $expert = User::factory()->create(['role' => 'expert']);
        $baseline = [
            ['type' => 'area', 'coords' => [[10.0, 124.0], [10.0, 124.01], [10.01, 124.01]]],
        ];
        $delineation = Delineation::create([
            'user_id' => $expert->id,
            'name' => 'North Shore',
            'notes' => 'Baseline zone',
            'features' => $baseline,
        ]);

        $this->actingAs($expert)->post(route('expert.annual-observations.store'), [
            'delineation_id' => $delineation->id,
            'year' => now()->year,
            'observation_date' => now()->toDateString(),
            'coverage_area_ha' => 12.5,
            'source' => 'Field survey',
            'features' => $baseline,
        ])->assertRedirect(route('expert.annual-observations.create'));

        $this->assertDatabaseCount('delineation_annual_observation_boundary_revisions', 0);
    }

    public function test_residents_can_read_their_own_annual_observations(): void
    {
        $resident = User::factory()->create(['role' => 'end_user']);
        $delineation = Delineation::create([
            'user_id' => $resident->id,
            'name' => 'North Shore',
            'notes' => 'Resident zone',
            'features' => [],
        ]);
        $delineation->annualObservations()->create([
            'created_by' => $resident->id,
            'year' => 2025,
            'observation_date' => '2025-06-01',
            'coverage_area_ha' => 8.75,
            'source' => 'Resident field survey',
        ]);

        $this->actingAs($resident)
            ->getJson(route('delineations.annual-observations.index', $delineation))
            ->assertOk()
            ->assertJsonPath('coverage.0.year', 2025)
            ->assertJsonPath('coverage.0.coverageAreaHa', 8.75);
    }

    public function test_residents_can_read_approved_annual_observations_from_another_user(): void
    {
        $resident = User::factory()->create(['role' => 'end_user']);
        $owner = User::factory()->create(['role' => 'end_user']);
        $delineation = Delineation::create([
            'user_id' => $owner->id,
            'name' => 'Approved Shore',
            'features' => [],
            'is_approved' => true,
            'is_rejected' => false,
        ]);
        $delineation->annualObservations()->create([
            'created_by' => $owner->id,
            'year' => 2025,
            'observation_date' => '2025-06-01',
            'coverage_area_ha' => 10.25,
            'source' => 'Expert review',
        ]);

        $this->actingAs($resident)
            ->getJson(route('delineations.annual-observations.index', $delineation))
            ->assertOk()
            ->assertJsonPath('coverage.0.coverageAreaHa', 10.25);
    }

    public function test_residents_cannot_read_another_users_unapproved_annual_observations(): void
    {
        $resident = User::factory()->create(['role' => 'end_user']);
        $owner = User::factory()->create(['role' => 'end_user']);
        $delineation = Delineation::create([
            'user_id' => $owner->id,
            'name' => 'Pending Shore',
            'features' => [],
            'is_approved' => false,
            'is_rejected' => false,
        ]);

        $this->actingAs($resident)
            ->getJson(route('delineations.annual-observations.index', $delineation))
            ->assertNotFound();
    }

    public function test_expert_map_history_contains_each_years_geometry_and_source(): void
    {
        $expert = User::factory()->create(['role' => 'expert']);
        $baseline = [
            ['type' => 'area', 'coords' => [[10.0, 124.0], [10.0, 124.01], [10.01, 124.01]]],
        ];
        $observed = [
            ['type' => 'area', 'coords' => [[10.0, 124.0], [10.0, 124.02], [10.01, 124.01]]],
        ];
        $delineation = Delineation::create([
            'user_id' => $expert->id,
            'name' => 'North Shore',
            'features' => $baseline,
        ]);
        $observation = $delineation->annualObservations()->create([
            'created_by' => $expert->id,
            'year' => 2024,
            'observation_date' => '2024-06-01',
            'coverage_area_ha' => 12.5,
            'source' => 'Field survey',
        ]);
        $observation->boundaryRevisions()->create([
            'version' => 1,
            'features' => $observed,
            'created_by' => $expert->id,
        ]);
        $delineation->annualObservations()->create([
            'created_by' => $expert->id,
            'year' => 2025,
            'observation_date' => '2025-06-01',
            'coverage_area_ha' => 13.0,
            'source' => 'Satellite review',
        ]);

        $this->actingAs($expert)
            ->getJson(route('delineations.annual-observations.index', $delineation))
            ->assertOk()
            ->assertJsonPath('coverage.0.year', 2024)
            ->assertJsonPath('coverage.0.coverageAreaHa', 12.5)
            ->assertJsonPath('coverage.0.source', 'Field survey')
            ->assertJsonPath('coverage.0.features', $observed)
            ->assertJsonPath('coverage.1.year', 2025)
            ->assertJsonPath('coverage.1.features', $observed);
    }
}
