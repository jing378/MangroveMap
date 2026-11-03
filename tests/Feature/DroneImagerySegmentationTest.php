<?php

namespace Tests\Feature;

use App\Jobs\SegmentDroneImagery;
use App\Models\DroneImageryLayer;
use App\Models\User;
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
            app(\App\Services\GdalCommandRunner::class),
            app(\App\Services\MangroveSegmentationService::class),
            app(\App\Services\SegmentationFeatureBuilder::class),
        );

        $layer->refresh();
        $this->assertSame('failed', $layer->segmentation_status);
        $this->assertStringContainsString('mosaic', $layer->segmentation_error);
        // The imagery itself stays usable.
        $this->assertSame('ready', $layer->status);
    }
}
