<?php

namespace Tests\Feature;

use App\Jobs\FinalizeDroneImagery;
use App\Jobs\SegmentDroneImagery;
use App\Models\DroneImageryLayer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DroneImageryReviewTest extends TestCase
{
    use RefreshDatabase;

    private const BOUNDS = ['west' => 124.0, 'south' => 9.99, 'east' => 124.01, 'north' => 10.0];

    private function reviewLayer(User $owner, array $attributes = []): DroneImageryLayer
    {
        $layer = DroneImageryLayer::create([
            'user_id' => $owner->id,
            'name' => 'Survey A',
            'status' => 'awaiting_review',
            'source_type' => 'single',
            'bounds' => self::BOUNDS,
            'preview_bounds' => self::BOUNDS,
            ...$attributes,
        ]);
        $layer->update([
            'mosaic_path' => "drone-imagery/processed/{$layer->id}/mosaic_3857.tif",
            'preview_path' => "drone-imagery/processed/{$layer->id}/preview.png",
        ]);
        Storage::disk('local')->put($layer->preview_path, 'png-bytes');

        return $layer;
    }

    /** Mangrove areas to submit plus the first confirm's delineation details (genus is required). */
    private function submittable(DroneImageryLayer $layer): array
    {
        $path = "drone-imagery/processed/{$layer->id}/ai_extent.json";
        Storage::disk('local')->put($path, json_encode([
            'features' => [['type' => 'area', 'coords' => [[9.999, 124.001], [9.999, 124.004], [9.995, 124.004]]]],
        ]));
        $layer->update(['segmentation_status' => 'ready', 'segmentation_path' => $path]);

        return ['delineation' => ['genera' => ['Rhizophora']]];
    }

    private function editsPayload(array $overrides = []): array
    {
        return [
            'mode' => 'remove',
            'polygons' => [[[124.001, 9.999], [124.004, 9.999], [124.004, 9.995]]],
            'offset' => ['east_m' => 1.5, 'north_m' => 0],
            'opacity' => 0.6,
            ...$overrides,
        ];
    }

    public function test_owner_can_open_the_editor_and_preview(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $layer = $this->reviewLayer($owner);

        $this->actingAs($owner)->get(route('drone-imagery.edit', $layer->id))
            ->assertOk()
            ->assertSee('Confirm &amp; submit for review', false)
            ->assertSee(str_replace('/', '\/', route('drone-imagery.preview', $layer->id)), false);

        $this->actingAs($owner)->get(route('drone-imagery.preview', $layer->id))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_other_users_cannot_open_the_editor_preview_or_save(): void
    {
        Storage::fake('local');
        $layer = $this->reviewLayer(User::factory()->create(['role' => 'expert']));
        $other = User::factory()->create();

        $this->actingAs($other)->get(route('drone-imagery.edit', $layer->id))->assertNotFound();
        $this->actingAs($other)->get(route('drone-imagery.preview', $layer->id))->assertNotFound();
        $this->actingAs($other)->putJson(route('drone-imagery.edits.update', $layer->id), $this->editsPayload())->assertNotFound();
        $this->actingAs($other)->postJson(route('drone-imagery.confirm', $layer->id), $this->editsPayload())->assertNotFound();
    }

    public function test_draft_layers_are_not_shared_with_residents(): void
    {
        Storage::fake('local');
        $expert = User::factory()->create(['role' => 'expert']);
        $this->reviewLayer($expert);
        $resident = User::factory()->create();

        $this->actingAs($resident)->getJson(route('drone-imagery.index'))
            ->assertOk()
            ->assertJsonCount(0, 'layers');

        $this->actingAs($expert)->getJson(route('drone-imagery.index'))
            ->assertOk()
            ->assertJsonPath('layers.0.status', 'awaiting_review')
            ->assertJsonPath('layers.0.tile_url', null)
            ->assertJsonPath('layers.0.review_url', route('drone-imagery.edit', DroneImageryLayer::sole()->id));
    }

    public function test_saving_a_draft_stores_normalized_edits_and_opacity(): void
    {
        Storage::fake('local');
        Queue::fake();
        $owner = User::factory()->create();
        $layer = $this->reviewLayer($owner);
        $closedRing = [[124.001, 9.999], [124.004, 9.999], [124.004, 9.995], [124.001, 9.999]];

        $this->actingAs($owner)
            ->putJson(route('drone-imagery.edits.update', $layer->id), $this->editsPayload(['polygons' => [$closedRing]]))
            ->assertOk();

        $layer->refresh();
        $this->assertSame('awaiting_review', $layer->status);
        $this->assertCount(3, $layer->edits['polygons'][0]);
        $this->assertSame(1.5, (float) $layer->edits['offset']['east_m']);
        $this->assertSame(0.6, $layer->display_opacity);
        Queue::assertNothingPushed();
    }

    public function test_invalid_edits_are_rejected(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $layer = $this->reviewLayer($owner);
        $url = route('drone-imagery.edits.update', $layer->id);

        $this->actingAs($owner)->putJson($url, $this->editsPayload(['polygons' => [[[125.5, 9.99], [125.6, 9.99], [125.6, 9.98]]]]))
            ->assertUnprocessable()->assertJsonValidationErrors('polygons');
        $this->actingAs($owner)->putJson($url, $this->editsPayload(['polygons' => [[[124.001, 9.999], [124.004, 9.999]]]]))
            ->assertUnprocessable()->assertJsonValidationErrors('polygons');
        $this->actingAs($owner)->putJson($url, $this->editsPayload(['offset' => ['east_m' => 500, 'north_m' => 0]]))
            ->assertUnprocessable()->assertJsonValidationErrors('offset.east_m');
        $this->actingAs($owner)->putJson($url, $this->editsPayload(['mode' => 'crop']))
            ->assertUnprocessable()->assertJsonValidationErrors('mode');
    }

    public function test_confirm_queues_finalizing(): void
    {
        Storage::fake('local');
        Queue::fake();
        $owner = User::factory()->create();
        $layer = $this->reviewLayer($owner);
        $details = $this->submittable($layer);

        $this->actingAs($owner)
            ->postJson(route('drone-imagery.confirm', $layer->id), $this->editsPayload($details))
            ->assertAccepted()
            ->assertJsonPath('status', 'finalizing')
            ->assertJsonPath('redirect_url', route('drone-imagery.page', ['layer' => $layer->id]));

        Queue::assertPushedOn('drone-imagery', FinalizeDroneImagery::class, fn ($job) => $job->layerId === $layer->id);
        $this->assertSame('finalizing', $layer->fresh()->status);
    }

    public function test_keep_mode_needs_at_least_one_area(): void
    {
        Storage::fake('local');
        Queue::fake();
        $owner = User::factory()->create();
        $layer = $this->reviewLayer($owner);

        $this->actingAs($owner)
            ->postJson(route('drone-imagery.confirm', $layer->id), $this->editsPayload(['mode' => 'keep', 'polygons' => []]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('polygons');

        Queue::assertNothingPushed();
        $this->assertSame('awaiting_review', $layer->fresh()->status);
    }

    public function test_confirm_is_blocked_while_processing_or_segmenting(): void
    {
        Storage::fake('local');
        Queue::fake();
        $owner = User::factory()->create();
        $finalizing = $this->reviewLayer($owner, ['status' => 'finalizing']);
        $segmenting = $this->reviewLayer($owner, ['status' => 'ready', 'segmentation_status' => 'processing']);

        $this->actingAs($owner)->postJson(route('drone-imagery.confirm', $finalizing->id), $this->editsPayload())->assertConflict();
        $this->actingAs($owner)->postJson(route('drone-imagery.confirm', $segmenting->id), $this->editsPayload())->assertConflict();
        Queue::assertNothingPushed();
    }

    public function test_ready_layer_reports_saved_edits_that_are_not_applied(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $layer = $this->reviewLayer($owner, [
            'status' => 'ready',
            'applied_edits' => DroneImageryLayer::EMPTY_EDITS,
        ]);

        $this->assertFalse($layer->hasPendingEdits());

        $this->actingAs($owner)
            ->putJson(route('drone-imagery.edits.update', $layer->id), $this->editsPayload())
            ->assertOk()
            ->assertJsonPath('has_pending_edits', true);

        // Opacity alone applies without re-tiling.
        $this->actingAs($owner)
            ->putJson(route('drone-imagery.edits.update', $layer->id), [
                ...$this->editsPayload(),
                'polygons' => [],
                'offset' => ['east_m' => 0, 'north_m' => 0],
                'opacity' => 0.4,
            ])
            ->assertOk()
            ->assertJsonPath('has_pending_edits', false);
        $this->assertSame(0.4, $layer->fresh()->display_opacity);
    }

    public function test_owner_sees_the_ai_result_while_reviewing_but_residents_do_not(): void
    {
        Storage::fake('local');
        $expert = User::factory()->create(['role' => 'expert']);
        $layer = $this->reviewLayer($expert);
        $resultPath = "drone-imagery/processed/{$layer->id}/ai_extent.json";
        Storage::disk('local')->put($resultPath, json_encode([
            'features' => [['type' => 'area', 'coords' => [[9.999, 124.001], [9.999, 124.004], [9.995, 124.004]]]],
            'stats' => ['mangrove_area_ha' => 0.5],
        ]));
        $layer->update(['segmentation_status' => 'ready', 'segmentation_path' => $resultPath, 'segmented_at' => now()]);

        $this->actingAs($expert)->get(route('drone-imagery.edit', $layer->id))
            ->assertOk()
            ->assertSee(str_replace('/', '\/', route('drone-imagery.segmentation.show', $layer->id)), false);
        $this->actingAs($expert)->getJson(route('drone-imagery.segmentation.show', $layer->id))
            ->assertOk()
            ->assertJsonCount(1, 'features');

        $resident = User::factory()->create();
        $this->actingAs($resident)->getJson(route('drone-imagery.segmentation.show', $layer->id))->assertNotFound();
    }

    public function test_owner_can_run_ai_detection_while_reviewing(): void
    {
        Storage::fake('local');
        Queue::fake();
        $owner = User::factory()->create();
        $layer = $this->reviewLayer($owner);

        $this->actingAs($owner)->postJson(route('drone-imagery.segmentation.store', $layer->id))
            ->assertAccepted()
            ->assertJsonPath('segmentation_status', 'queued');

        Queue::assertPushed(SegmentDroneImagery::class, fn ($job) => $job->layerId === $layer->id);
    }

    public function test_editor_sends_layers_that_are_still_processing_back_to_drone_upload(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $layer = $this->reviewLayer($owner, ['status' => 'processing']);

        $this->actingAs($owner)->get(route('drone-imagery.edit', $layer->id))
            ->assertRedirect(route('drone-imagery.page', ['layer' => $layer->id]));
    }

    public function test_owner_can_save_corrected_mangrove_areas(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $layer = $this->reviewLayer($owner, [
            'segmentation_status' => 'ready',
            'segmentation_stats' => ['mangrove_area_ha' => 9.9, 'surveyed_area_ha' => 10, 'edited' => false],
            'segmented_at' => now()->subHour(),
        ]);
        // ~100 m x ~110 m square (about 1.1 ha) in [lng, lat] order, the same as the imagery shapes.
        $square = [[124.001, 9.999], [124.00191, 9.999], [124.00191, 9.998], [124.001, 9.998]];

        $this->actingAs($owner)
            ->putJson(route('drone-imagery.segmentation.update', $layer->id), [
                'areas' => [$square],
                'offset' => ['east_m' => 2, 'north_m' => 0],
            ])
            ->assertOk()
            ->assertJsonPath('segmentation_status', 'ready')
            ->assertJsonPath('segmentation_stats.edited', true)
            ->assertJsonPath('segmentation_stats.feature_count', 1);

        $layer->refresh();
        $this->assertTrue($layer->isSegmentationEdited());
        $this->assertEqualsWithDelta(1.1, $layer->segmentation_stats['mangrove_area_ha'], 0.05);
        $this->assertEqualsWithDelta(11, $layer->segmentation_stats['mangrove_coverage_percent'], 0.6);
        $this->assertSame(2.0, (float) $layer->segmentation_stats['offset']['east_m']);
        $this->assertTrue($layer->segmented_at->isAfter(now()->subMinute()));

        // The corrected areas are what the map, "Use as delineation" and the editor load ([lat, lng] order).
        $this->actingAs($owner)->getJson(route('drone-imagery.segmentation.show', $layer->id))
            ->assertOk()
            ->assertJsonCount(1, 'features')
            ->assertJsonPath('features.0.type', 'area')
            ->assertJsonPath('features.0.coords.0', [9.999, 124.001]);
    }

    public function test_all_mangrove_areas_can_be_removed(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $layer = $this->reviewLayer($owner, ['segmentation_status' => 'ready', 'segmentation_stats' => ['mangrove_area_ha' => 3]]);

        $this->actingAs($owner)
            ->putJson(route('drone-imagery.segmentation.update', $layer->id), ['areas' => [], 'offset' => ['east_m' => 0, 'north_m' => 0]])
            ->assertOk()
            ->assertJsonPath('segmentation_stats.feature_count', 0)
            ->assertJsonPath('segmentation_stats.mangrove_area_ha', 0);
    }

    public function test_mangrove_areas_cannot_be_edited_by_others_while_ai_runs_or_when_invalid(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $layer = $this->reviewLayer($owner, ['segmentation_status' => 'ready']);
        $running = $this->reviewLayer($owner, ['segmentation_status' => 'processing']);
        $url = fn ($id) => route('drone-imagery.segmentation.update', $id);
        $valid = ['areas' => [[[124.001, 9.999], [124.004, 9.999], [124.004, 9.995]]], 'offset' => ['east_m' => 0, 'north_m' => 0]];

        $this->actingAs(User::factory()->create())->putJson($url($layer->id), $valid)->assertNotFound();
        $this->actingAs($owner)->putJson($url($running->id), $valid)->assertConflict();
        $this->actingAs($owner)->putJson($url($layer->id), [...$valid, 'areas' => [[[130, 9.999], [130.1, 9.999], [130.1, 9.99]]]])
            ->assertUnprocessable()->assertJsonValidationErrors('areas');
        $this->assertNull($layer->fresh()->segmentation_stats);
    }

    public function test_cropping_to_the_mangrove_areas_needs_at_least_one_area(): void
    {
        Storage::fake('local');
        Queue::fake();
        $owner = User::factory()->create();
        $layer = $this->reviewLayer($owner);

        $this->actingAs($owner)
            ->postJson(route('drone-imagery.confirm', $layer->id), $this->editsPayload(['mode' => 'keep_ai', 'polygons' => []]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('polygons');

        $this->actingAs($owner)
            ->postJson(route('drone-imagery.confirm', $layer->id), $this->editsPayload(['mode' => 'keep_ai', ...$this->submittable($layer)]))
            ->assertAccepted();
        $this->assertSame('keep_ai', $layer->fresh()->edits['mode']);
        Queue::assertPushed(FinalizeDroneImagery::class);
    }

    public function test_missing_ai_detection_is_queued_when_the_owner_opens_drone_upload_or_the_editor(): void
    {
        Storage::fake('local');
        Queue::fake();
        config(['ml.drone.auto_segment' => true]);
        $owner = User::factory()->create();
        $first = $this->reviewLayer($owner);
        $second = $this->reviewLayer($owner);
        $failed = $this->reviewLayer($owner, ['segmentation_status' => 'failed']);
        foreach ([$first, $second, $failed] as $layer) {
            Storage::disk('local')->put($layer->mosaic_path, 'tif');
        }

        $this->actingAs($owner)->get(route('drone-imagery.page'))->assertOk();
        $this->assertSame('queued', $first->fresh()->segmentation_status);
        $this->assertSame('queued', $second->fresh()->segmentation_status);
        $this->assertSame('failed', $failed->fresh()->segmentation_status); // retried only on request

        $third = $this->reviewLayer($owner);
        Storage::disk('local')->put($third->mosaic_path, 'tif');
        $this->actingAs($owner)->get(route('drone-imagery.edit', $third->id))->assertOk();
        $this->assertSame('queued', $third->fresh()->segmentation_status);

        Queue::assertPushed(SegmentDroneImagery::class, 3);
    }

    public function test_awaiting_review_layer_can_be_deleted(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $layer = $this->reviewLayer($owner);

        $this->actingAs($owner)->deleteJson(route('drone-imagery.destroy', $layer->id))
            ->assertOk()
            ->assertJsonPath('action', 'deleted');
        $this->assertModelMissing($layer);
    }
}
