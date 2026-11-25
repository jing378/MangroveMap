<?php

namespace Tests\Feature;

use App\Models\Delineation;
use App\Models\DroneImageryLayer;
use App\Models\User;
use App\Services\ExtentAccuracyReport;
use App\Services\MangroveComparison;
use App\Services\SatelliteMangroveGrid;
use App\Support\ExtentOverlap;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MangroveComparisonTest extends TestCase
{
    use RefreshDatabase;

    /** Satellite grid of 0.0001° pixels from 124.0 E / 10.001 N. */
    private const PIXEL = 0.0001;

    private const BOUNDS = ['west' => 124.0, 'south' => 10.0, 'east' => 124.001, 'north' => 10.001];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        // A small satellite file: rows 0-9, columns 0-4 were mangrove in every year 2020-2025.
        $runs = '';
        for ($row = 0; $row < 10; $row++) {
            $runs .= pack('vvvvVV', $row, 0, 5, 1, 0b111111, 0);
        }
        $path = storage_path('framework/testing/mangrove-history-comparison.json');
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, json_encode([
            'source' => 'Global Mangrove Watch v4.1',
            'source_url' => 'https://doi.org/10.5281/zenodo.21346456',
            'license' => 'CC BY 4.0',
            'province' => 'Southern Leyte',
            'years' => [2020, 2021, 2022, 2023, 2024, 2025],
            'total_ha' => [40, 41, 42, 43, 44, 45],
            'municipalities' => [
                ['id' => 1, 'name' => 'Bontoc', 'ha' => [30, 31, 32, 33, 34, 35]],
                ['id' => 2, 'name' => 'Anahawan', 'ha' => [0, 0, 0, 0, 0, 0]],
            ],
            'grid' => ['west' => 124.0, 'north' => 10.001, 'pixel' => self::PIXEL, 'width' => 20, 'height' => 20],
            'runs' => base64_encode($runs),
        ]));
        config(['map.mangrove_history.path' => $path]);
    }

    /** Stored-feature ring (Leaflet [lat, lng]) over grid columns $fromCol..$toCol-1 and rows 0-9. */
    private function area(int $fromCol, int $toCol, ?string $municipality = null): array
    {
        $west = 124.0 + $fromCol * self::PIXEL;
        $east = 124.0 + $toCol * self::PIXEL;

        return [
            'type' => 'area',
            'coords' => [[10.001, $west], [10.001, $east], [10.0, $east], [10.0, $west]],
            ...($municipality ? ['location_details' => ['municipality' => ['name' => $municipality]]] : []),
        ];
    }

    /** A drone layer whose AI drew columns 0-9; $current is the saved ai_extent.json (null: same as the AI's). */
    private function droneLayer(User $owner, ?array $current = null, string $model = 'roboflow', bool $keepOriginal = true): DroneImageryLayer
    {
        $layer = DroneImageryLayer::create([
            'user_id' => $owner->id,
            'name' => 'Survey',
            'status' => 'ready',
            'source_type' => 'single',
            'bounds' => self::BOUNDS,
            'preview_bounds' => self::BOUNDS,
        ]);
        $original = ['features' => [$this->area(0, 10)], 'stats' => ['extent_model' => $model, 'edited' => false, 'offset' => ['east_m' => 0, 'north_m' => 0]]];
        $path = "drone-imagery/processed/{$layer->id}/ai_extent.json";
        if ($keepOriginal) {
            Storage::disk('local')->put($layer->aiOriginalExtentPath(), json_encode($original));
        }
        Storage::disk('local')->put($path, json_encode($current ?? $original));
        $layer->update(['segmentation_status' => 'ready', 'segmentation_path' => $path]);

        return $layer;
    }

    public function test_satellite_agreement_measures_the_share_seen_as_mangrove(): void
    {
        $grid = app(SatelliteMangroveGrid::class);
        $shapes = ExtentOverlap::shapesFromFeatures([$this->area(0, 10)]);

        $result = $grid->agreement($shapes, 2030);

        $this->assertSame(2025, $result['year']); // nearest year the satellite has
        $this->assertEqualsWithDelta(0.5, $result['agreement'], 0.01);
        $this->assertEqualsWithDelta($result['area_ha'] / 2, $result['satellite_ha'], 0.01);
        $this->assertSame(0.0, $grid->agreement(ExtentOverlap::shapesFromFeatures([$this->area(5, 10)]), 2025)['agreement']);
    }

    public function test_ai_drafts_are_scored_against_the_best_reviewed_boundary(): void
    {
        $owner = User::factory()->create(['role' => 'end_user']);

        // 1. Submitted: the owner kept half of the AI's area (columns 0-4).
        $submitted = $this->droneLayer($owner, ['features' => [$this->area(0, 5)], 'stats' => ['edited' => true]]);
        Delineation::forceCreate(['user_id' => $owner->id, 'drone_imagery_layer_id' => $submitted->id, 'source' => 'drone', 'name' => 'A', 'features' => [$this->area(0, 5)]]);
        // 2. Corrected in the editor but not submitted yet.
        $this->droneLayer($owner, ['features' => [$this->area(0, 10)], 'stats' => ['edited' => true]], 'unet');
        // 3. Approved unchanged, from before the AI's drafts were kept (ai_extent.json never edited).
        $accepted = $this->droneLayer($owner, null, 'unet', keepOriginal: false);
        Delineation::forceCreate(['user_id' => $owner->id, 'drone_imagery_layer_id' => $accepted->id, 'source' => 'drone', 'name' => 'B', 'features' => [$this->area(0, 10)], 'is_approved' => true]);
        // Left out: rejected, not reviewed, and edited without a kept draft.
        $rejected = $this->droneLayer($owner);
        Delineation::forceCreate(['user_id' => $owner->id, 'drone_imagery_layer_id' => $rejected->id, 'source' => 'drone', 'name' => 'C', 'features' => [$this->area(0, 10)], 'is_rejected' => true]);
        $this->droneLayer($owner);
        $this->droneLayer($owner, ['features' => [$this->area(0, 3)], 'stats' => ['edited' => true]], keepOriginal: false);

        $report = app(ExtentAccuracyReport::class);
        $cases = collect($report->cases())->keyBy('layer_id');

        $this->assertCount(3, $cases);
        $this->assertSame('submitted', $cases[$submitted->id]['reference']);
        $this->assertEqualsWithDelta(0.5, $cases[$submitted->id]['iou'], 0.01);
        $this->assertEqualsWithDelta(0.5, $cases[$submitted->id]['precision'], 0.01);
        $this->assertEqualsWithDelta(1.0, $cases[$submitted->id]['recall'], 0.01);
        $this->assertTrue($cases[$submitted->id]['edited']);
        $this->assertSame('expert_approved', $cases[$accepted->id]['reference']);
        $this->assertEqualsWithDelta(1.0, $cases[$accepted->id]['iou'], 0.001);
        $this->assertFalse($cases[$accepted->id]['edited']);
        $this->assertSame(['submitted' => 1, 'owner_corrected' => 1, 'expert_approved' => 1], $report->summary()['by_reference']);
        $this->assertSame(1, $report->summary()['unchanged']);
    }

    public function test_the_position_nudge_is_not_counted_as_an_ai_error(): void
    {
        $owner = User::factory()->create(['role' => 'expert']);
        // The owner moved the imagery 20 m east; the areas moved with it, unchanged otherwise.
        $layer = $this->droneLayer($owner);
        $metresPerDegreeLng = ExtentOverlap::METRES_PER_DEGREE * cos(deg2rad(10.0005));
        $shift = 20 / $metresPerDegreeLng;
        $moved = $this->area(0, 10);
        $moved['coords'] = array_map(fn ($p) => [$p[0], $p[1] + $shift], $moved['coords']);
        $layer->update(['applied_edits' => ['offset' => ['east_m' => 20, 'north_m' => 0]]]);
        Delineation::forceCreate(['user_id' => $owner->id, 'drone_imagery_layer_id' => $layer->id, 'source' => 'drone', 'name' => 'A', 'features' => [$moved], 'is_approved' => true]);

        $case = app(ExtentAccuracyReport::class)->cases()[0];

        $this->assertEqualsWithDelta(1.0, $case['iou'], 0.005);
    }

    public function test_comparison_lists_official_figures_and_municipalities(): void
    {
        $resident = User::factory()->create(['role' => 'end_user']);
        Delineation::forceCreate(['user_id' => $resident->id, 'name' => 'Stand', 'features' => [$this->area(0, 10, 'Bontoc')], 'is_approved' => true, 'approved_at' => now()]);
        Delineation::forceCreate(['user_id' => $resident->id, 'name' => 'Sogod stand', 'features' => [$this->area(0, 10, 'Sogod')], 'is_approved' => true, 'approved_at' => now()]);

        $summary = app(MangroveComparison::class)->build();

        $this->assertSame(311216, collect($summary['references'])->firstWhere('key', 'philippines')['hectares']);
        $this->assertSame('Southern Leyte', $summary['province']['place']);
        $this->assertSame(40.0, $summary['satellite']['hectares']); // 2020
        $this->assertSame(45.0, $summary['satellite']['latest_hectares']);
        $this->assertEqualsWithDelta(0.5, $summary['agreement']['agreement'], 0.01);

        $places = collect($summary['municipalities'])->keyBy('name');
        $this->assertSame(['Bontoc', 'Sogod'], $places->keys()->all()); // Anahawan never had mangroves
        $this->assertEqualsWithDelta($places['Bontoc']['system_ha'] / 35 * 100, $places['Bontoc']['mapped_percent'], 0.1);
        $this->assertNull($places['Sogod']['mapped_percent']); // not on the satellite at all
        $this->assertSame(0.0, $places['Sogod']['satellite_latest_ha']);
    }

    public function test_dashboards_show_the_comparison(): void
    {
        $resident = User::factory()->create(['role' => 'end_user']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($resident)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Compared with official figures')
            ->assertSee('311,216')
            ->assertSee('Philippine Statistics Authority (PSA)')
            ->assertDontSee('Surveys checked');

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Compared with official figures')
            ->assertSee('942.77');
    }

    public function test_the_map_year_card_compares_with_the_satellite(): void
    {
        $resident = User::factory()->create(['role' => 'end_user']);
        $expert = User::factory()->create(['role' => 'expert']);
        Delineation::forceCreate(['user_id' => $resident->id, 'name' => 'Stand', 'features' => [$this->area(0, 10, 'Bontoc')], 'is_approved' => true, 'approved_at' => now()]);

        // The dashboard links to the map with the comparison on, for the whole province or one municipality.
        $this->actingAs($resident)->get(route('dashboard'))
            ->assertSee(route('map').'?compare=previous', false)
            ->assertSee(route('map').'?compare=previous&amp;municipality=Bontoc', false);
        $this->actingAs($expert)->get(route('expert.dashboard'))
            ->assertSee(route('expert.map').'?compare=previous', false);
        // One line under the Coverage Trend with the latest previous data (the fixture's 2025 total).
        $this->actingAs($resident)->get(route('dashboard'))
            ->assertSee('Previous data (satellite, 2025):')
            ->assertSee('<strong>45 ha</strong>', false)
            // The Coverage Trend's switch, with the province's previous data for its second scale.
            ->assertSee('id="trendPreviousToggle"', false)
            ->assertViewHas('previousDataTrend', fn ($trend) => $trend['years'] === [2020, 2021, 2022, 2023, 2024, 2025]
                && $trend['hectares'] === [40, 41, 42, 43, 44, 45]);

        // The map's "Mangroves mapped by year" card has the switch, and the agreement shares for its readout.
        $response = $this->actingAs($resident)->get(route('map'))
            ->assertOk()
            ->assertSee('id="mangroveHistorySatToggle"', false)
            ->assertSee(json_encode(route('map.mangrove-history')), false);
        $agreement = $response->viewData('satelliteAgreement');
        $this->assertEqualsWithDelta(0.5, $agreement['total'], 0.01);
        $this->assertEqualsWithDelta(0.5, $agreement['places']->Bontoc, 0.01);
    }

    public function test_changes_over_time_includes_the_previous_data_inside_the_area(): void
    {
        $resident = User::factory()->create(['role' => 'end_user']);
        // Columns 0-9: half of it (columns 0-4) was mangrove in every year 2020-2025.
        $delineation = Delineation::forceCreate(['user_id' => $resident->id, 'name' => 'Stand', 'features' => [$this->area(0, 10, 'Bontoc')], 'is_approved' => true, 'approved_at' => now()]);

        $response = $this->actingAs($resident)
            ->getJson(route('delineations.annual-observations.index', $delineation))
            ->assertOk();
        $previous = $response->json('previousData');

        // MangroveMap's own figure continues the timeline from the year the area was added.
        $this->assertSame(now()->year, $response->json('mapped.since'));
        $this->assertTrue($response->json('mapped.approved'));
        $this->assertEqualsWithDelta($previous['areaHa'], $response->json('mapped.areaHa'), 0.02);

        $this->assertSame('Global Mangrove Watch v4.1', $previous['source']);
        $this->assertSame([2020, 2021, 2022, 2023, 2024, 2025], array_column($previous['years'], 'year'));
        foreach ($previous['years'] as $year) {
            $this->assertEqualsWithDelta($previous['areaHa'] / 2, $year['mangroveHa'], 0.01);
        }

        // The map page has the timeline section and the year card's switch it turns on.
        $this->actingAs($resident)->get(route('map'))
            ->assertSee('id="timelinePrevious"', false)
            ->assertSee('id="timelinePreviousRange"', false)
            ->assertSee('id="timelinePreviousPlay"', false)
            // Year filters: the area timeline's and the year card's.
            ->assertSee('id="timelinePreviousFrom"', false)
            ->assertSee('id="mangroveHistoryFrom"', false)
            ->assertSee('window.showPreviousDataYear', false)
            ->assertSee('mangroveLayerClick', false) // amber pixels answer clicks that no delineation took
            ->assertSee('Compare with previous data');
    }

    public function test_changes_over_time_has_no_previous_data_without_the_satellite_file(): void
    {
        config(['map.mangrove_history.path' => storage_path('framework/testing/no-such-file.json')]);
        $resident = User::factory()->create(['role' => 'end_user']);
        $delineation = Delineation::forceCreate(['user_id' => $resident->id, 'name' => 'Stand', 'features' => [$this->area(0, 10)], 'is_approved' => true]);

        $this->actingAs($resident)
            ->getJson(route('delineations.annual-observations.index', $delineation))
            ->assertOk()
            ->assertJsonPath('previousData', null);
    }

    public function test_accuracy_command_prints_and_exports_the_report(): void
    {
        $owner = User::factory()->create(['role' => 'end_user']);
        $layer = $this->droneLayer($owner, ['features' => [$this->area(0, 5)], 'stats' => ['edited' => true]]);
        $markdown = storage_path('framework/testing/accuracy-report.md');
        $csv = storage_path('framework/testing/accuracy-report.csv');

        $this->artisan('mangroves:accuracy', ['--markdown' => $markdown, '--csv' => $csv])
            ->expectsOutputToContain('Pooled over 1 survey: IoU 0.500')
            ->assertSuccessful();

        $this->assertStringContainsString('## MangroveMap compared with official figures', file_get_contents($markdown));
        $this->assertStringContainsString("{$layer->id},Survey", file_get_contents($csv));
    }

    public function test_accuracy_command_scores_two_geojson_files(): void
    {
        $polygon = fn (int $toCol) => ['type' => 'Polygon', 'coordinates' => [[
            [124.0, 10.0], [124.0 + $toCol * self::PIXEL, 10.0], [124.0 + $toCol * self::PIXEL, 10.001], [124.0, 10.001], [124.0, 10.0],
        ]]];
        $predicted = storage_path('framework/testing/predicted.geojson');
        $reference = storage_path('framework/testing/reference.geojson');
        file_put_contents($predicted, json_encode($polygon(10)));
        file_put_contents($reference, json_encode($polygon(5)));

        $this->artisan('mangroves:accuracy', ['--predicted' => $predicted, '--reference' => $reference, '--year' => 2025])
            ->expectsOutputToContain('100.0% of the reference')
            ->assertSuccessful();
    }
}
