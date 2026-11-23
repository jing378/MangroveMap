<?php

namespace Tests\Feature;

use App\Models\Delineation;
use App\Models\DroneImageryLayer;
use App\Models\Feedback;
use App\Models\TileSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Map Layers' details and exports (what was the separate Datasets page), plus the admin dashboard figures. */
class AdminMapLayersExportTest extends TestCase
{
    use RefreshDatabase;

    /** A square of about 1.2 ha near Maasin, with its barangay and a genus. */
    private const AREA = [
        'type' => 'area',
        'coords' => [[10.1300, 124.8400], [10.1300, 124.8410], [10.1310, 124.8410], [10.1310, 124.8400]],
        'genera' => ['Rhizophora'],
        'location_details' => ['barangay' => ['name' => 'Tunga-tunga'], 'municipality' => ['name' => 'Maasin City']],
    ];

    private function delineation(User $user, array $attributes = []): Delineation
    {
        return Delineation::create(array_merge([
            'user_id' => $user->id,
            'name' => 'Stand',
            'features' => [self::AREA],
            'is_approved' => false,
            'is_rejected' => false,
        ], $attributes));
    }

    public function test_old_datasets_url_redirects_to_map_layers_and_only_admins_export(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get('/admin/datasets')
            ->assertRedirect('/admin/map-layers');

        $this->actingAs(User::factory()->create(['role' => 'end_user']))
            ->get(route('admin.map-layers.export'))
            ->assertRedirect();
    }

    public function test_tabs_show_location_area_genera_and_file_sizes(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'end_user', 'name' => 'Alice']);
        $this->delineation($owner, ['name' => 'Tunga-tunga stand', 'is_approved' => true, 'approved_at' => now()]);
        DroneImageryLayer::create(['user_id' => $owner->id, 'name' => 'Macrohon survey', 'status' => 'ready', 'source_type' => 'single']);

        $this->actingAs($admin)->get(route('admin.map-layers.index'))
            ->assertOk()
            ->assertSee('Tunga-tunga stand')
            ->assertSee('Maasin City')
            ->assertSee('1.22 ha')
            ->assertSee('Rhizophora')
            ->assertSee('Export CSV')
            ->assertDontSee('Tile Explorer'); // private dataset uploads are not map layers

        $this->get(route('admin.map-layers.index', ['tab' => 'imagery']))->assertOk()->assertSee('Macrohon survey');
    }

    public function test_search_finds_delineations_by_place_name(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'end_user']);
        $this->delineation($owner, ['name' => 'In Maasin']);
        $this->delineation($owner, ['name' => 'Elsewhere', 'features' => [['type' => 'area', 'coords' => self::AREA['coords']]]]);

        $this->actingAs($admin)->get(route('admin.map-layers.index', ['search' => 'Tunga']))
            ->assertOk()->assertSee('In Maasin')->assertDontSee('Elsewhere');
    }

    public function test_csv_export_has_every_matching_delineation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'end_user', 'name' => 'Alice']);
        $this->delineation($owner, ['name' => 'Approved stand', 'is_approved' => true]);
        $this->delineation($owner, ['name' => 'Waiting stand']);

        $response = $this->actingAs($admin)->get(route('admin.map-layers.export', ['status' => 'published']));

        $response->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $csv = $response->streamedContent();
        $this->assertStringContainsString('ID,Name,Status,Source,Owner', $csv);
        $this->assertStringContainsString('"Approved stand",Published,Hand-drawn,Alice', $csv);
        $this->assertStringContainsString('"Maasin City",Tunga-tunga,1.2', $csv);
        $this->assertStringNotContainsString('Waiting stand', $csv);
    }

    public function test_imagery_exports_as_csv(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'end_user']);
        DroneImageryLayer::create(['user_id' => $owner->id, 'name' => 'Macrohon survey', 'status' => 'ready', 'source_type' => 'single']);

        $csv = $this->actingAs($admin)->get(route('admin.map-layers.export', ['tab' => 'imagery']))->streamedContent();
        $this->assertStringContainsString('ID,Name,Stage,Owner', $csv);
        $this->assertStringContainsString('"Macrohon survey","Ready, not submitted"', $csv);
    }

    public function test_geojson_export_writes_lng_lat_polygons_of_published_areas(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'end_user']);
        $approved = $this->delineation($owner, ['name' => 'Approved stand', 'is_approved' => true]);
        $this->delineation($owner, ['name' => 'Waiting stand']);

        $response = $this->actingAs($admin)->get(route('admin.map-layers.export', ['format' => 'geojson']));

        $response->assertOk();
        $geojson = json_decode($response->streamedContent(), true);
        $this->assertSame('FeatureCollection', $geojson['type']);
        $this->assertCount(1, $geojson['features']);
        $feature = $geojson['features'][0];
        $this->assertSame($approved->id, $feature['properties']['delineation_id']);
        $this->assertSame(['Rhizophora'], $feature['properties']['genera']);
        $ring = $feature['geometry']['coordinates'][0];
        $this->assertSame([124.84, 10.13], $ring[0]);
        $this->assertSame($ring[0], end($ring)); // closed ring
    }

    public function test_dashboard_shows_real_figures_and_what_needs_attention(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $owner = User::factory()->create(['role' => 'end_user']);
        $this->delineation($owner, ['is_approved' => true]);
        $old = $this->delineation($owner);
        $old->forceFill(['created_at' => now()->subDays(5)])->save();
        DroneImageryLayer::create(['user_id' => $owner->id, 'name' => 'Broken', 'status' => 'failed', 'source_type' => 'single']);
        // AI draft the owner left for 10 days, and a fresh one (not yet worth a warning).
        DroneImageryLayer::create(['user_id' => $owner->id, 'name' => 'Old draft', 'status' => 'awaiting_review', 'source_type' => 'single', 'segmentation_status' => 'ready', 'segmented_at' => now()->subDays(10)]);
        DroneImageryLayer::create(['user_id' => $owner->id, 'name' => 'New draft', 'status' => 'awaiting_review', 'source_type' => 'single', 'segmentation_status' => 'ready', 'segmented_at' => now()->subDay()]);
        Feedback::create(['user_id' => $owner->id, 'type' => 'bug', 'message' => 'Map is slow', 'status' => 'new']);
        TileSource::create(['user_id' => $owner->id, 'name' => 'Bontoc tiles', 'original_name' => 'bontoc.tif', 'stored_path' => 'x.tif', 'file_size' => 3 * 1024 * 1024]);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk()
            ->assertViewHas('pendingDelineations', 1)
            ->assertViewHas('droneFailed', 1)
            ->assertViewHas('approvedAreas', 1)
            ->assertViewHas('approvedHectares', fn ($hectares) => $hectares > 1 && $hectares < 1.5)
            ->assertSee('Needs attention')
            ->assertSee('1 drone layer failed')
            ->assertSee('1 delineation waiting for experts')
            ->assertSee('1 new feedback message')
            ->assertSee('1 AI draft not confirmed')
            ->assertSee('2 awaiting owner')
            ->assertSee('3 MB')        // Tile Explorer storage
            ->assertSee('(1 upload)')
            ->assertDontSee('Analysis Success Rate');
    }
}
