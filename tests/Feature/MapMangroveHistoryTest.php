<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\MangroveHistoryStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** "Mangrove extent by year" on the map: Global Mangrove Watch's yearly maps, served from a file in the repository. */
class MapMangroveHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_installed_dataset_covers_every_year_and_adds_up(): void
    {
        $history = json_decode(file_get_contents(config('map.mangrove_history.path')), true);

        $years = $history['years'];
        $this->assertSame(range($years[0], end($years)), $years);
        $this->assertCount(count($years), $history['total_ha']);
        $this->assertSame(0, strlen(base64_decode($history['runs'], true)) % 16);
        $this->assertSame($history['run_count'] * 16, strlen(base64_decode($history['runs'], true)));

        // The municipalities split the province total (each rounded to 0.1 ha).
        foreach ($years as $index => $year) {
            $sum = array_sum(array_map(fn ($place) => $place['ha'][$index], $history['municipalities']));
            $this->assertEqualsWithDelta($history['total_ha'][$index], $sum, 0.1 * count($history['municipalities']), "Year {$year}");
        }
    }

    public function test_endpoint_serves_the_dataset_to_guests_and_compresses_it_when_asked(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'mh');
        file_put_contents($path, json_encode(['years' => [2024, 2025], 'total_ha' => [10.5, 11], 'runs' => '']));
        config(['map.mangrove_history.path' => $path]);

        $this->getJson(route('map.mangrove-history'))
            ->assertOk()
            ->assertHeaderMissing('Content-Encoding')
            ->assertJsonPath('years', [2024, 2025]);

        $compressed = $this->get(route('map.mangrove-history'), ['Accept-Encoding' => 'gzip, deflate']);
        $compressed->assertOk()->assertHeader('Content-Encoding', 'gzip');
        $this->assertSame([10.5, 11], json_decode(gzdecode($compressed->getContent()), true)['total_ha']);

        unlink($path);
    }

    public function test_endpoint_reports_a_missing_dataset(): void
    {
        config(['map.mangrove_history.path' => storage_path('framework/testing/no-such-file.json')]);

        $this->getJson(route('map.mangrove-history'))->assertNotFound();
    }

    public function test_dashboard_charts_the_satellite_hectares_per_year(): void
    {
        // buildDashboardData uses MySQL's YEAR(); provide it for the in-memory SQLite test DB.
        DB::connection()->getPdo()
            ->sqliteCreateFunction('YEAR', fn ($date) => $date ? (int) substr($date, 0, 4) : null, 1);

        $summary = app(MangroveHistoryStats::class)->summary();
        $this->assertSame(1985, $summary['years'][0]);
        $this->assertCount(count($summary['years']), $summary['total_ha']);
        $this->assertArrayNotHasKey('runs', $summary);
        // Places that never had mangroves are left out of the picker.
        $this->assertNotContains('Anahawan', array_column($summary['municipalities'], 'name'));
        $this->assertContains('Bontoc', array_column($summary['municipalities'], 'name'));

        $resident = User::factory()->create(['role' => 'end_user']);

        // Hidden by default: no satellite card.
        $this->actingAs($resident)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('id="satelliteExtentChart"', false);

        config(['map.mangrove_history.enabled' => true]);

        $this->actingAs($resident)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('id="satelliteExtentChart"', false)
            ->assertSee('id="satelliteExtentPlace"', false)
            ->assertSee('Global Mangrove Watch v4.1');

        // Without the file the dashboard still loads, without the card.
        config(['map.mangrove_history.path' => storage_path('framework/testing/no-such-file.json')]);
        $this->assertNull(app(MangroveHistoryStats::class)->summary());

        $this->actingAs($resident)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('id="satelliteExtentChart"', false);
    }

    public function test_map_page_has_the_year_slider_and_delineate_page_does_not(): void
    {
        DB::connection()->getPdo()
            ->sqliteCreateFunction('YEAR', fn ($date) => $date ? (int) substr($date, 0, 4) : null, 1);
        $resident = User::factory()->create(['role' => 'end_user']);

        // An approved area in Bontoc: the map gets its municipality, for the slider's place picker.
        \App\Models\Delineation::create([
            'user_id' => $resident->id,
            'name' => 'Stand',
            'is_approved' => true,
            'approved_at' => now(),
            'features' => [[
                'type' => 'area',
                'coords' => [[10.38, 125.01], [10.38, 125.0191], [10.389, 125.0191]],
                'genera' => ['Rhizophora'],
                'location_details' => ['municipality' => ['name' => 'Bontoc']],
            ]],
        ]);
        $this->assertSame(
            'Bontoc',
            $this->actingAs($resident)->getJson(route('map.delineations'))->json('delineations.0.municipality'),
        );

        // Satellite data hidden (the default): the slider runs on the system's own records.
        $this->actingAs($resident)->get(route('map'))
            ->assertOk()
            ->assertSee('id="mangroveHistoryToggle"', false)
            ->assertSee('id="mangroveHistoryRange"', false)
            ->assertSee('Mangroves mapped by year')
            ->assertSee('window.refreshMapTimeline', false)
            ->assertDontSee('Global Mangrove Watch v4.1')
            ->assertDontSee(route('map.mangrove-history'), false);

        config(['map.mangrove_history.enabled' => true]);

        $this->actingAs($resident)->get(route('map'))
            ->assertOk()
            ->assertSee('id="mangroveHistoryToggle"', false)
            ->assertSee('id="mangroveHistoryRange"', false)
            ->assertSee('id="mangroveHistoryCard"', false)
            ->assertSee('Global Mangrove Watch v4.1')
            ->assertDontSee('window.refreshMapTimeline = ', false);

        $this->actingAs($resident)->get(route('delineate'))
            ->assertOk()
            ->assertDontSee('id="mangroveHistoryToggle"', false)
            ->assertDontSee('id="mangroveHistoryCard"', false);
    }
}
