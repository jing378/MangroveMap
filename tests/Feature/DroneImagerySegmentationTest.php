<?php

namespace Tests\Feature;

use App\Jobs\SegmentDroneImagery;
use App\Models\DroneImageryLayer;
use App\Models\User;
use App\Services\GdalCommandRunner;
use App\Services\MangroveSegmentationService;
use App\Services\SegmentationFeatureBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DroneImagerySegmentationTest extends TestCase
{
    use RefreshDatabase;

    private function readyLayer(User $owner, array $attributes = []): DroneImageryLayer
    {
        $layer = DroneImageryLayer::create(array_merge([
            'user_id' => $owner->id,
            'name' => 'Coastal survey',
            'status' => 'ready',
            'source_type' => 'batch',
            'bounds' => ['west' => 124.0, 'south' => 9.9, 'east' => 124.1, 'north' => 10.0],
            'zoom_min' => 12,
            'zoom_max' => 20,
        ], $attributes));
        $layer->update([
            'tiles_path' => "drone-imagery/tiles/{$layer->id}",
            'mosaic_path' => "drone-imagery/processed/{$layer->id}/mosaic_3857.tif",
        ]);

        return $layer->fresh();
    }

    private function storeSegmentation(DroneImageryLayer $layer): void
    {
        $path = "drone-imagery/processed/{$layer->id}/ai_extent.json";
        Storage::disk('local')->put($path, json_encode([
            'features' => [[
                'type' => 'area',
                'coords' => [[9.95, 124.05], [9.95, 124.06], [9.96, 124.06], [9.95, 124.05]],
                'area_m2' => 1200.5,
                'genera' => [],
            ]],
            'stats' => ['mangrove_area_ha' => 0.12],
        ]));
        $layer->update([
            'segmentation_status' => 'ready',
            'segmentation_path' => $path,
            'segmentation_stats' => ['mangrove_area_ha' => 0.12, 'mangrove_coverage_percent' => 30.5],
            'segmented_at' => now(),
        ]);
    }

    public function test_layer_list_and_segmentation_endpoint_return_ai_extent_to_viewers(): void
    {
        Storage::fake('local');
        $expert = User::factory()->create(['role' => 'expert']);
        $resident = User::factory()->create(['role' => 'end_user']);
        $layer = $this->readyLayer($expert);
        $this->storeSegmentation($layer);

        $this->actingAs($expert)
            ->get(route('drone-imagery.index'))
            ->assertOk()
            ->assertJsonPath('layers.0.segmentation_status', 'ready')
            ->assertJsonPath('layers.0.segmentation_stats.mangrove_area_ha', 0.12)
            ->assertJsonPath('layers.0.segmentation_url', route('drone-imagery.segmentation.show', $layer->id));

        // Residents can view AI extents of shared expert layers.
        $this->actingAs($resident)
            ->get(route('drone-imagery.segmentation.show', $layer->id))
            ->assertOk()
            ->assertJsonCount(1, 'features')
            ->assertJsonPath('features.0.type', 'area')
            ->assertJsonPath('features.0.coords.0', [9.95, 124.05]);
    }

    public function test_pinpointed_canopies_are_served_to_viewers_of_the_layer_only(): void
    {
        Storage::fake('local');
        $expert = User::factory()->create(['role' => 'expert']);
        $resident = User::factory()->create(['role' => 'end_user']);
        $layer = $this->readyLayer($expert);
        $this->storeSegmentation($layer);

        // No canopies: the payload says so and the endpoint is a 404.
        $this->actingAs($expert)
            ->get(route('drone-imagery.index'))
            ->assertOk()
            ->assertJsonPath('layers.0.canopies_url', null);
        $this->actingAs($expert)
            ->get(route('drone-imagery.canopies.show', $layer->id))
            ->assertNotFound();

        Storage::disk('local')->put($layer->canopiesPath(), json_encode([
            'count' => 2, 'kept' => 2, 'by_genus' => ['Rhizophora' => 2],
            'canopies' => [
                ['genus' => 'Rhizophora', 'confidence' => 0.83, 'area_m2' => 12.4, 'center' => [9.951, 124.051], 'coords' => [[9.95, 124.05], [9.95, 124.052], [9.952, 124.052]]],
                ['genus' => 'Rhizophora', 'confidence' => 0.41, 'area_m2' => 3.1, 'center' => [9.953, 124.053], 'coords' => []],
            ],
        ]));
        $layer->update(['segmentation_stats' => $layer->segmentation_stats + ['canopy_count' => 2, 'canopies_by_genus' => ['Rhizophora' => 2], 'offset' => ['east_m' => 0, 'north_m' => 0]]]);

        $this->actingAs($expert)
            ->get(route('drone-imagery.index'))
            ->assertOk()
            ->assertJsonPath('layers.0.canopies_url', route('drone-imagery.canopies.show', $layer->id))
            ->assertJsonPath('layers.0.segmentation_stats.canopy_count', 2);

        // Residents can see the canopies of shared expert layers, like the extent.
        $this->actingAs($resident)
            ->get(route('drone-imagery.canopies.show', $layer->id))
            ->assertOk()
            ->assertJsonPath('count', 2)
            ->assertJsonPath('by_genus.Rhizophora', 2)
            ->assertJsonCount(2, 'canopies')
            ->assertJsonPath('canopies.0.genus', 'Rhizophora')
            ->assertJsonPath('canopies.0.center', [9.951, 124.051])
            ->assertJsonPath('offset.east_m', 0);

        // A resident's own unpublished layer stays private.
        $private = $this->readyLayer($resident);
        $this->storeSegmentation($private);
        Storage::disk('local')->put($private->canopiesPath(), json_encode(['count' => 0, 'kept' => 0, 'by_genus' => [], 'canopies' => []]));
        $this->actingAs(User::factory()->create(['role' => 'end_user']))
            ->get(route('drone-imagery.canopies.show', $private->id))
            ->assertNotFound();
        $this->actingAs($resident)
            ->get(route('drone-imagery.canopies.show', $private->id))
            ->assertOk()
            ->assertJsonPath('count', 0);
    }

    public function test_the_owner_can_remove_a_pinpointed_canopy(): void
    {
        Storage::fake('local');
        $resident = User::factory()->create(['role' => 'end_user']);
        $layer = $this->readyLayer($resident, ['status' => 'awaiting_review']);
        $layer->update([
            'preview_path' => "drone-imagery/processed/{$layer->id}/preview.png",
            'preview_bounds' => ['west' => 124.0, 'south' => 9.9, 'east' => 124.1, 'north' => 10.0],
        ]);
        $this->storeSegmentation($layer);
        Storage::disk('local')->put($layer->canopiesPath(), json_encode([
            'count' => 3, 'kept' => 3, 'by_genus' => ['Rhizophora' => 2, 'Avicennia' => 1],
            'canopies' => [
                ['genus' => 'Rhizophora', 'confidence' => 0.83, 'center' => [9.951, 124.051]],
                ['genus' => 'Avicennia', 'confidence' => 0.62, 'center' => [9.952, 124.052]],
                ['genus' => 'Rhizophora', 'confidence' => 0.41, 'center' => [9.953, 124.053]],
            ],
        ]));
        $layer->update(['segmentation_stats' => $layer->segmentation_stats + ['canopy_count' => 3, 'canopies_by_genus' => ['Rhizophora' => 2, 'Avicennia' => 1]]]);
        $url = route('drone-imagery.canopies.update', $layer->id);

        // Someone else cannot remove them.
        $this->actingAs(User::factory()->create(['role' => 'end_user']))
            ->putJson($url, ['removed' => [[9.952, 124.052]]])
            ->assertNotFound();

        // Save draft sends every removed pin at once; a centre that matches nothing is ignored.
        $this->actingAs($resident)
            ->putJson($url, ['removed' => [[9.952, 124.052], [9.953, 124.053], [9.5, 124.5]]])
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonCount(1, 'canopies')
            ->assertJsonPath('canopies.0.center', [9.951, 124.051])
            ->assertJsonPath('by_genus.Avicennia', 0)
            ->assertJsonPath('by_genus.Rhizophora', 1);

        $layer->refresh();
        $this->assertSame(1, $layer->segmentation_stats['canopy_count']);
        $this->assertSame(0, $layer->segmentation_stats['canopies_by_genus']['Avicennia']);
        $stored = json_decode(Storage::disk('local')->get($layer->canopiesPath()), true);
        $this->assertSame(['Rhizophora'], array_column($stored['canopies'], 'genus'));
        $extent = json_decode(Storage::disk('local')->get($layer->segmentation_path), true);
        $this->assertSame(1, $extent['stats']['canopy_count']);

        // Sending the same removals again changes nothing.
        $this->actingAs($resident)
            ->putJson($url, ['removed' => [[9.952, 124.052]]])
            ->assertOk()
            ->assertJsonPath('count', 1);

        // A canopy made of separate patches: each patch is removed on its own (named by its first point).
        $patchA = [[9.9, 124.0], [9.9, 124.002], [9.902, 124.002], [9.902, 124.0]];      // 4 units
        $patchB = [[9.95, 124.05], [9.95, 124.051], [9.951, 124.051], [9.951, 124.05]];  // 1 unit
        Storage::disk('local')->put($layer->canopiesPath(), json_encode([
            'count' => 1, 'kept' => 1, 'by_genus' => ['Sonneratia' => 1],
            'canopies' => [['genus' => 'Sonneratia', 'confidence' => 0.6, 'area_m2' => 500.0, 'center' => [9.92, 124.02], 'rings' => [$patchA, $patchB], 'coords' => $patchA]],
        ]));
        $this->actingAs($resident)
            ->putJson($url, ['removed' => [], 'removed_rings' => [$patchA[0]]])
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonCount(1, 'canopies.0.rings')
            ->assertJsonPath('canopies.0.rings.0', $patchB)
            ->assertJsonPath('canopies.0.area_m2', 100)
            ->assertJsonPath('canopies.0.center', [9.9505, 124.0505]);
        // Its last patch removed: the canopy is gone.
        $this->actingAs($resident)
            ->putJson($url, ['removed' => [], 'removed_rings' => [$patchB[0]]])
            ->assertOk()
            ->assertJsonPath('count', 0)
            ->assertJsonCount(0, 'canopies')
            ->assertJsonPath('by_genus.Sonneratia', 0);

        // Not while the AI is running.
        $layer->update(['segmentation_status' => 'processing']);
        $this->actingAs($resident)
            ->putJson($url, ['removed' => [[9.951, 124.051]]])
            ->assertStatus(409);
    }

    public function test_segmentation_is_hidden_from_users_who_cannot_see_the_layer_or_before_it_is_ready(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create(['role' => 'end_user']);
        $layer = $this->readyLayer($owner);

        $this->actingAs($owner)
            ->get(route('drone-imagery.segmentation.show', $layer->id))
            ->assertNotFound();

        $this->storeSegmentation($layer);
        $this->actingAs(User::factory()->create(['role' => 'end_user']))
            ->get(route('drone-imagery.segmentation.show', $layer->id))
            ->assertNotFound();
    }

    public function test_owner_can_queue_segmentation_for_ready_layer(): void
    {
        Queue::fake();
        $owner = User::factory()->create(['role' => 'expert']);
        $layer = $this->readyLayer($owner);

        $this->actingAs($owner)
            ->post(route('drone-imagery.segmentation.store', $layer->id))
            ->assertAccepted()
            ->assertJsonPath('segmentation_status', 'queued');

        Queue::assertPushedOn('drone-imagery', SegmentDroneImagery::class);
        $this->assertSame('queued', $layer->fresh()->segmentation_status);

        // A second request while queued is rejected.
        $this->actingAs($owner)
            ->post(route('drone-imagery.segmentation.store', $layer->id))
            ->assertStatus(409);
    }

    public function test_only_owner_can_queue_segmentation_and_only_after_processing(): void
    {
        Queue::fake();
        $owner = User::factory()->create(['role' => 'expert']);
        $layer = $this->readyLayer($owner);

        $this->actingAs(User::factory()->create(['role' => 'expert']))
            ->post(route('drone-imagery.segmentation.store', $layer->id))
            ->assertNotFound();

        $processing = $this->readyLayer($owner, ['status' => 'processing']);
        $this->actingAs($owner)
            ->post(route('drone-imagery.segmentation.store', $processing->id))
            ->assertStatus(409);

        Queue::assertNothingPushed();
    }

    public function test_layer_cannot_be_deleted_while_segmentation_runs(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create(['role' => 'expert']);
        $layer = $this->readyLayer($owner, ['segmentation_status' => 'processing']);

        $this->actingAs($owner)
            ->delete(route('drone-imagery.destroy', $layer->id))
            ->assertStatus(409);

        $this->assertDatabaseHas('drone_imagery_layers', ['id' => $layer->id]);
    }

    public function test_job_marks_segmentation_failed_when_mosaic_is_missing(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create(['role' => 'expert']);
        $layer = $this->readyLayer($owner, ['segmentation_status' => 'queued']);

        (new SegmentDroneImagery($layer->id))->handle(
            app(GdalCommandRunner::class),
            app(MangroveSegmentationService::class),
            app(SegmentationFeatureBuilder::class),
        );

        $layer->refresh();
        $this->assertSame('failed', $layer->segmentation_status);
        $this->assertStringContainsString('mosaic', $layer->segmentation_error);
        // The imagery itself stays usable.
        $this->assertSame('ready', $layer->status);
    }
}
