<?php

namespace Tests\Feature;

use App\Models\Delineation;
use App\Models\User;
use App\Services\ApprovedCoverageStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardCoverageStatsTest extends TestCase
{
    use RefreshDatabase;

    /** Roughly 1 km × 1 km square near Sogod Bay. */
    private function square(array $genera = []): array
    {
        return [[
            'type' => 'area',
            'coords' => [[10.38, 125.01], [10.38, 125.0191], [10.389, 125.0191], [10.389, 125.01]],
            'genera' => $genera,
        ]];
    }

    private function delineation(User $user, array $attributes): Delineation
    {
        return Delineation::create(array_merge([
            'user_id' => $user->id,
            'name' => 'Stand',
            'is_approved' => false,
            'is_rejected' => false,
        ], $attributes));
    }

    public function test_dashboard_coverage_comes_from_approved_delineations_only(): void
    {
        $viewer = User::factory()->create(['role' => 'end_user']);
        $other = User::factory()->create(['role' => 'end_user']);

        $approved = ['is_approved' => true, 'approved_at' => now()];
        $this->delineation($other, $approved + ['features' => $this->square(['Rhizophora', 'Avicennia'])]);
        $this->delineation($viewer, $approved + ['features' => $this->square(['Rhizophora'])]);
        // Pending and rejected areas are not counted.
        $this->delineation($other, ['features' => $this->square(['Nypa'])]);
        $this->delineation($other, ['is_rejected' => true, 'features' => $this->square(['Ceriops'])]);

        $oneSquare = ApprovedCoverageStats::areaHectares($this->square());
        $this->assertGreaterThan(90, $oneSquare);

        $response = $this->actingAs($viewer)->get(route('dashboard'))->assertOk();

        $this->assertEqualsWithDelta(2 * $oneSquare, $response->viewData('totalCoverage'), 0.01);
        $response->assertViewHas('approvedAreaCount', 2)
            ->assertViewHas('genusCount', 2)
            ->assertViewHas('genusLabels', ['Rhizophora', 'Avicennia'])
            ->assertViewHas('genusSeries', [2, 1])
            ->assertViewHas('trendYears', [now()->year])
            ->assertViewHas('coverageChangePct', null)
            ->assertSee('2 approved areas')
            ->assertDontSee('recovery');
    }

    public function test_trend_is_split_by_municipality_for_the_map_year_slider(): void
    {
        $user = User::factory()->create(['role' => 'end_user']);
        $inPlace = fn (string $name) => array_map(
            fn (array $feature) => $feature + ['location_details' => ['municipality' => ['name' => $name]]],
            $this->square(),
        );
        $approved = ['is_approved' => true, 'approved_at' => now()];

        $this->delineation($user, $approved + ['features' => $inPlace('Sogod')]);
        $this->delineation($user, $approved + ['features' => $inPlace('Sogod')]);
        $this->delineation($user, $approved + ['features' => $inPlace('Bontoc')]);
        // No barangay matched: in the overall trend only. Pending: nowhere.
        $this->delineation($user, $approved + ['features' => $this->square()]);
        $this->delineation($user, ['features' => $inPlace('Maasin City')]);

        $oneSquare = ApprovedCoverageStats::areaHectares($this->square());
        $summary = app(ApprovedCoverageStats::class)->summary();
        $year = now()->year;

        $this->assertSame(['Bontoc', 'Sogod'], array_keys($summary['trend_by_municipality']));
        $this->assertEqualsWithDelta(4 * $oneSquare, $summary['trend'][$year], 0.01);
        $this->assertEqualsWithDelta(2 * $oneSquare, $summary['trend_by_municipality']['Sogod'][$year], 0.01);
        $this->assertEqualsWithDelta($oneSquare, $summary['trend_by_municipality']['Bontoc'][$year], 0.01);
        $this->assertSame(array_keys($summary['trend']), array_keys($summary['trend_by_municipality']['Sogod']));
    }

    public function test_empty_system_shows_zero_and_no_sample_charts(): void
    {
        $viewer = User::factory()->create(['role' => 'end_user']);

        $this->actingAs($viewer)->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('totalCoverage', 0)
            ->assertViewHas('genusCount', 0)
            ->assertSee('No approved mangrove areas yet.')
            ->assertDontSee('47,382');
    }
}
