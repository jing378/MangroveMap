<?php

namespace Tests\Feature;

use App\Models\Delineation;
use App\Models\DroneImageryLayer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardChartsTest extends TestCase
{
    use RefreshDatabase;

    private const AREA = [[
        'type' => 'area',
        'coords' => [[10.0, 124.0], [10.0, 124.01], [10.01, 124.01], [10.0, 124.0]],
        'genera' => ['Rhizophora'],
        'location_details' => ['municipality' => ['name' => 'Maasin City'], 'barangay' => ['name' => 'Tunga-tunga']],
    ]];

    private function delineation(User $owner, array $attributes = []): Delineation
    {
        return Delineation::forceCreate(array_merge([
            'user_id' => $owner->id,
            'name' => 'Stand',
            'features' => self::AREA,
        ], $attributes));
    }

    public function test_dashboard_shows_the_three_overview_charts_with_links(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $resident = User::factory()->create(['role' => 'end_user']);
        $expert = User::factory()->create(['role' => 'expert']);

        // Reviews: one approved after 4 hours, one pending, one rejected after 2 hours, and an expert's own
        // (approved as it was saved), which must not count toward the average.
        $this->delineation($resident, ['is_approved' => true, 'approved_by' => $expert->id, 'created_at' => now()->subHours(10), 'approved_at' => now()->subHours(6)]);
        $this->delineation($resident);
        $this->delineation($resident, ['is_rejected' => true, 'created_at' => now()->subHours(5), 'rejected_at' => now()->subHours(3)]);
        $this->delineation($expert, ['is_approved' => true, 'approved_by' => $expert->id, 'approved_at' => now(), 'source' => 'drone']);

        DroneImageryLayer::create(['user_id' => $resident->id, 'name' => 'Waiting', 'status' => 'awaiting_review', 'source_type' => 'single', 'segmentation_status' => 'ready']);
        DroneImageryLayer::create(['user_id' => $resident->id, 'name' => 'Broken', 'status' => 'failed', 'source_type' => 'single']);

        $response = $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();

        // 1. Drone pipeline: every stage in order, each linking to its filter.
        $stages = $response->viewData('droneStages');
        $this->assertSame(array_keys(DroneImageryLayer::STAGES), array_column($stages, 'key'));
        $this->assertSame(1, collect($stages)->firstWhere('key', 'waiting_owner')['count']);
        $this->assertSame(1, collect($stages)->firstWhere('key', 'failed')['count']);
        $response->assertSee('Drone survey pipeline')
            ->assertSee(e(route('admin.map-layers.index', ['tab' => 'imagery', 'status' => 'waiting_owner'])), false);

        // 2. Reviews: counts per state, the average (3 hours: 4 and 2) without the expert's own, and the sources.
        $review = $response->viewData('reviewStatus');
        $this->assertSame([2, 1, 1], array_column($review['segments'], 'count'));
        $this->assertSame(3.0, $review['average_hours']);
        $this->assertSame(2, $review['reviewed']);
        $this->assertSame(['manual' => 3, 'drone' => 1], $review['sources']);
        $response->assertSee('Delineation reviews')
            ->assertSee('3.0 hours')
            ->assertSee(route('admin.map-layers.index', ['status' => 'pending']), false);

        // 3. Hectares by municipality (approved only), linking to that search.
        $rows = $response->viewData('hectaresByMunicipality');
        $this->assertSame('Maasin City', $rows[0]['name']);
        $this->assertSame(2, $rows[0]['area_count']);
        $response->assertSee('Hectares by municipality')
            ->assertSee(e(route('admin.map-layers.index', ['status' => 'published', 'search' => 'Maasin City'])), false);
    }

    public function test_charts_say_so_when_there_is_nothing_yet(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('No drone surveys uploaded yet.')
            ->assertSee('No delineations submitted yet.')
            ->assertSee('No approved areas yet.');
    }

    public function test_municipalities_past_the_limit_fold_into_other(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $expert = User::factory()->create(['role' => 'expert']);
        foreach (range(1, 10) as $i) {
            $features = self::AREA;
            $features[0]['location_details']['municipality']['name'] = "Town {$i}";
            $this->delineation($expert, ['is_approved' => true, 'approved_by' => $expert->id, 'features' => $features]);
        }

        $rows = $this->actingAs($admin)->get(route('admin.dashboard'))->viewData('hectaresByMunicipality');

        $this->assertCount(9, $rows); // 8 named + "Other (2)"
        $this->assertSame('Other (2)', $rows[8]['name']);
        $this->assertTrue($rows[8]['muted']);
        $this->assertNull($rows[8]['url']);
    }
}
