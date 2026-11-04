<?php

namespace Tests\Feature;

use App\Models\Delineation;
use App\Models\DelineationAnnualObservation;
use App\Models\User;
use App\Services\DelineationObservationRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DelineationAnnualObservationBoundaryRevisionTest extends TestCase
{
    use RefreshDatabase;

    // Non-whole coordinates: JSON turns 10.0 into 10, which would break strict array comparisons.
    private const BASELINE = [
        ['type' => 'area', 'coords' => [[10.001, 124.001], [10.001, 124.011], [10.011, 124.011]], 'genera' => ['Rhizophora']],
    ];

    private const GROWN = [
        ['type' => 'area', 'coords' => [[10.001, 124.001], [10.001, 124.021], [10.011, 124.011]], 'genera' => ['Rhizophora', 'Avicennia']],
    ];

    private function residentDelineation(array $attributes = []): Delineation
    {
        $resident = User::factory()->create(['role' => 'end_user']);

        $delineation = Delineation::create(array_merge([
            'user_id' => $resident->id,
            'name' => 'North Shore',
            'features' => self::BASELINE,
            'is_approved' => true,
            'is_rejected' => false,
        ], $attributes));

        // Drawn in an earlier year so the first observed change also gets a baseline year.
        $delineation->forceFill(['created_at' => now()->subYears(2)])->save();

        return $delineation;
    }

    private function saveBoundary(User $user, Delineation $delineation, array $extra = [])
    {
        return $this->actingAs($user)->putJson(route('delineations.boundary.update', $delineation), array_merge([
            'features' => self::GROWN,
        ], $extra));
    }

    public function test_correction_edit_does_not_create_an_observation(): void
    {
        $expert = User::factory()->create(['role' => 'expert']);
        $delineation = $this->residentDelineation();

        $this->saveBoundary($expert, $delineation, ['record_as' => 'correction'])
            ->assertOk()
            ->assertJsonPath('observation_year', null);

        $this->assertDatabaseCount('delineation_annual_observations', 0);
        $this->assertDatabaseCount('delineation_revisions', 1);
    }

    public function test_edit_without_choice_is_treated_as_correction(): void
    {
        $expert = User::factory()->create(['role' => 'expert']);

        $this->saveBoundary($expert, $this->residentDelineation())->assertOk();

        $this->assertDatabaseCount('delineation_annual_observations', 0);
    }

    public function test_observed_change_records_year_area_genera_and_baseline(): void
    {
        $expert = User::factory()->create(['role' => 'expert']);
        $delineation = $this->residentDelineation();
        $observedOn = now()->subMonth()->toDateString();

        $this->saveBoundary($expert, $delineation, [
            'record_as' => 'observation',
            'observation_date' => $observedOn,
            'observation_source' => 'drone_imagery',
        ])->assertOk()->assertJsonPath('observation_year', now()->subMonth()->year);

        $observations = $delineation->annualObservations()->orderBy('year')->get();
        $this->assertCount(2, $observations);

        [$baseline, $observed] = $observations;
        $this->assertSame(now()->subYears(2)->year, $baseline->year);
        $this->assertSame(DelineationAnnualObservation::BASELINE_SOURCE, $baseline->source);
        $this->assertEqualsWithDelta(DelineationObservationRecorder::areaHectares(self::BASELINE), $baseline->coverage_area_ha, 0.0001);

        $this->assertSame($observedOn, $observed->observation_date->toDateString());
        $this->assertSame('Drone imagery', $observed->source);
        $this->assertSame(['Rhizophora', 'Avicennia'], $observed->genera_present);
        $this->assertEqualsWithDelta(DelineationObservationRecorder::areaHectares(self::GROWN), $observed->coverage_area_ha, 0.0001);
        $this->assertGreaterThan($baseline->coverage_area_ha, $observed->coverage_area_ha);
        $this->assertSame($expert->id, $observed->created_by);
        $this->assertSame(1, $observed->boundaryRevisions()->count());

        // The delineation itself now shows the new boundary.
        $this->assertSame(self::GROWN[0]['coords'], $delineation->fresh()->features[0]['coords']);
    }

    public function test_second_observed_change_in_same_year_replaces_figures(): void
    {
        $expert = User::factory()->create(['role' => 'expert']);
        $delineation = $this->residentDelineation();
        $choice = ['record_as' => 'observation', 'observation_date' => now()->toDateString(), 'observation_source' => 'field_survey'];

        $this->saveBoundary($expert, $delineation, $choice)->assertOk();
        $this->actingAs($expert)->putJson(route('delineations.boundary.update', $delineation), array_merge([
            'features' => self::BASELINE,
        ], $choice))->assertOk();

        $thisYear = $delineation->annualObservations()->where('year', now()->year)->firstOrFail();
        $this->assertSame(2, $delineation->annualObservations()->count(), 'baseline + this year only');
        $this->assertEqualsWithDelta(DelineationObservationRecorder::areaHectares(self::BASELINE), $thisYear->coverage_area_ha, 0.0001);
        $this->assertSame(2, $thisYear->boundaryRevisions()->count());
    }

    public function test_observation_in_the_year_the_area_was_drawn_has_no_separate_baseline(): void
    {
        $expert = User::factory()->create(['role' => 'expert']);
        $delineation = $this->residentDelineation();
        $delineation->forceFill(['created_at' => now()])->save();

        $this->saveBoundary($expert, $delineation, [
            'record_as' => 'observation',
            'observation_date' => now()->toDateString(),
            'observation_source' => 'field_survey',
        ])->assertOk();

        $this->assertSame(1, $delineation->annualObservations()->count());
    }

    public function test_observed_change_requires_a_valid_past_date_and_source(): void
    {
        $expert = User::factory()->create(['role' => 'expert']);
        $delineation = $this->residentDelineation();

        $this->saveBoundary($expert, $delineation, ['record_as' => 'observation'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['observation_date', 'observation_source']);

        $this->saveBoundary($expert, $delineation, [
            'record_as' => 'observation',
            'observation_date' => now(DelineationAnnualObservation::LOCAL_TIMEZONE)->addDays(2)->toDateString(),
            'observation_source' => 'field_survey',
        ])->assertStatus(422)->assertJsonValidationErrors(['observation_date']);

        $this->assertDatabaseCount('delineation_annual_observations', 0);
        $this->assertSame(self::BASELINE[0]['coords'], $delineation->fresh()->features[0]['coords']);
    }

    public function test_residents_cannot_record_observed_changes(): void
    {
        $resident = User::factory()->create(['role' => 'end_user']);
        $delineation = Delineation::create([
            'user_id' => $resident->id,
            'name' => 'My pending area',
            'features' => self::BASELINE,
        ]);

        $this->saveBoundary($resident, $delineation, [
            'record_as' => 'observation',
            'observation_date' => now()->toDateString(),
            'observation_source' => 'field_survey',
        ])->assertForbidden();

        $this->assertDatabaseCount('delineation_annual_observations', 0);
    }

    public function test_observed_change_needs_an_area_shape(): void
    {
        $expert = User::factory()->create(['role' => 'expert']);
        $delineation = $this->residentDelineation();

        $this->saveBoundary($expert, $delineation, [
            'features' => [['type' => 'line', 'coords' => [[10.001, 124.001], [10.011, 124.011]]]],
            'record_as' => 'observation',
            'observation_date' => now()->toDateString(),
            'observation_source' => 'field_survey',
        ])->assertStatus(422)->assertJsonValidationErrors(['record_as']);
    }

    public function test_area_matches_the_map_formula(): void
    {
        // About 1 km x 1 km near the equator: roughly 100 ha.
        $square = [['type' => 'area', 'coords' => [[0.0, 0.0], [0.0, 0.009], [0.009, 0.009], [0.009, 0.0]]]];

        $this->assertEqualsWithDelta(100.1, DelineationObservationRecorder::areaHectares($square), 0.5);
    }

    public function test_history_endpoint_returns_genera_present(): void
    {
        $expert = User::factory()->create(['role' => 'expert']);
        $delineation = $this->residentDelineation();

        $this->saveBoundary($expert, $delineation, [
            'record_as' => 'observation',
            'observation_date' => now()->toDateString(),
            'observation_source' => 'satellite_imagery',
        ])->assertOk();

        $this->actingAs($expert)
            ->getJson(route('delineations.annual-observations.index', $delineation))
            ->assertOk()
            ->assertJsonCount(2, 'coverage')
            ->assertJsonPath('coverage.1.source', 'Satellite imagery')
            ->assertJsonPath('coverage.1.features.0.coords', self::GROWN[0]['coords'])
            ->assertJsonPath('generaPresent', ['Rhizophora', 'Avicennia']);
    }

    public function test_old_annual_observations_page_is_gone(): void
    {
        $expert = User::factory()->create(['role' => 'expert']);

        $this->actingAs($expert)->get('/expert/annual-observations')->assertNotFound();
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
        $delineation = Delineation::create([
            'user_id' => $expert->id,
            'name' => 'North Shore',
            'features' => self::BASELINE,
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
            'features' => self::GROWN,
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
            ->assertJsonPath('coverage.0.features', self::GROWN)
            ->assertJsonPath('coverage.1.year', 2025)
            ->assertJsonPath('coverage.1.features', self::GROWN);
    }
}
