<?php

namespace Tests\Feature;

use App\Jobs\ProcessDroneImagery;
use App\Models\DroneImageryLayer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DroneImageryUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_upload_single_tiff_to_private_storage(): void
    {
        Storage::fake('local');
        Queue::fake();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('drone-imagery.store'), [
            'name' => 'Survey area A',
            'files' => [UploadedFile::fake()->create('survey.tif', 100)],
        ]);

        $response->assertAccepted()
            ->assertJsonPath('name', 'Survey area A')
            ->assertJsonPath('status', 'queued')
            ->assertJsonPath('source_type', 'single')
            ->assertJsonPath('file_count', 1);

        $layer = DroneImageryLayer::with('files')->sole();
        Queue::assertPushedOn('drone-imagery', ProcessDroneImagery::class);
        $this->assertSame($user->id, $layer->user_id);
        $this->assertSame('single', $layer->source_type);
        $this->assertCount(1, $layer->files);
        Storage::disk('local')->assertExists($layer->files[0]->stored_path);
    }

    public function test_user_can_upload_tiff_larger_than_500_mib(): void
    {
        Storage::fake('local');
        Queue::fake();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('drone-imagery.store'), [
            'files' => [UploadedFile::fake()->create('large-survey.tif', 900 * 1024)],
        ]);

        $response->assertAccepted()
            ->assertJsonPath('status', 'queued');
    }

    public function test_user_can_upload_batch_and_only_owner_can_read_layer_status(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();

        $response = $this->actingAs($owner)->post(route('drone-imagery.store'), [
            'files' => [
                UploadedFile::fake()->create('north.tif', 100),
                UploadedFile::fake()->create('south.tiff', 100),
            ],
        ]);

        $response->assertAccepted()
            ->assertJsonPath('source_type', 'batch')
            ->assertJsonPath('file_count', 2);

        $layerId = $response->json('id');
        $this->get(route('drone-imagery.show', $layerId))->assertOk()
            ->assertJsonCount(2, 'files');

        $this->actingAs(User::factory()->create())
            ->get(route('drone-imagery.show', $layerId))
            ->assertNotFound();
    }

    public function test_upload_rejects_non_tiff_extensions(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('drone-imagery.store'), [
            'files' => [UploadedFile::fake()->create('survey.jpg', 100)],
        ])->assertSessionHasErrors('files.0');

        $this->assertDatabaseCount('drone_imagery_layers', 0);
    }

    public function test_upload_rejects_more_files_than_the_batch_limit(): void
    {
        Storage::fake('local');
        Queue::fake();
        config(['gdal.max_upload_files' => 3]);
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('drone-imagery.store'), [
            'files' => array_map(fn ($i) => UploadedFile::fake()->create("tile_{$i}.tif", 10), range(1, 4)),
        ])->assertUnprocessable()->assertJsonValidationErrors('files');

        $this->assertDatabaseCount('drone_imagery_layers', 0);
        Queue::assertNothingPushed();
    }

    public function test_ready_layer_lists_bounds_and_serves_only_its_owner_xyz_tiles(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $layer = DroneImageryLayer::create([
            'user_id' => $owner->id,
            'name' => 'Coastal survey',
            'status' => 'ready',
            'source_type' => 'batch',
            'bounds' => [
                'west' => 124.0,
                'south' => 9.9,
                'east' => 124.1,
                'north' => 10.0,
            ],
            'tiles_path' => "drone-imagery/tiles/{$owner->id}",
            'zoom_min' => 12,
            'zoom_max' => 20,
        ]);
        Storage::disk('local')->put("{$layer->tiles_path}/12/3458/1933.png", 'png-test-data');

        $this->actingAs($owner)
            ->get(route('drone-imagery.index'))
            ->assertOk()
            ->assertJsonPath('layers.0.bounds.west', 124)
            ->assertJsonPath('layers.0.zoom_min', 12)
            ->assertJsonPath('layers.0.uploaded_by', $owner->name)
            ->assertJsonPath('layers.0.tile_url', url("/drone-imagery/{$layer->id}/tiles/{z}/{x}/{y}"));

        $tileResponse = $this->actingAs($owner)
            ->get(route('drone-imagery.tile', [$layer->id, 12, 3458, 1933]));
        $tileResponse
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
        $this->assertStringContainsString('private', $tileResponse->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=86400', $tileResponse->headers->get('Cache-Control'));

        $this->actingAs(User::factory()->create())
            ->get(route('drone-imagery.tile', [$layer->id, 12, 3458, 1933]))
            ->assertNotFound();

        $this->actingAs($owner)
            ->get(route('drone-imagery.tile', [$layer->id, 21, 0, 0]))
            ->assertNotFound();
    }

    public function test_empty_tile_inside_layer_is_fully_transparent(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $layer = DroneImageryLayer::create([
            'user_id' => $owner->id,
            'name' => 'Coastal survey',
            'status' => 'ready',
            'source_type' => 'single',
            'bounds' => ['west' => 124.0, 'south' => 9.9, 'east' => 124.1, 'north' => 10.0],
            'zoom_min' => 12,
            'zoom_max' => 20,
        ]);
        $layer->update(['tiles_path' => "drone-imagery/tiles/{$layer->id}"]);

        // No tile file exists here (gdal2tiles skips empty tiles), so the placeholder is served.
        $response = $this->actingAs($owner)->get(route('drone-imagery.tile', [$layer->id, 12, 3458, 1933]));
        $response->assertOk()->assertHeader('Content-Type', 'image/png');

        $png = $response->getContent();
        if (! extension_loaded('gd')) {
            $this->assertSame(base64_decode(\App\Http\Controllers\DroneImageryController::TRANSPARENT_PNG), $png);

            return;
        }

        $image = imagecreatefromstring($png);
        $alpha = (imagecolorat($image, 0, 0) >> 24) & 0x7F;
        $this->assertSame(127, $alpha, 'Placeholder tile must be fully transparent (GD alpha 127).');
    }

    public function test_residents_can_render_ready_expert_tiffs_but_not_other_residents_uploads(): void
    {
        Storage::fake('local');
        $resident = User::factory()->create(['role' => 'end_user']);
        $otherResident = User::factory()->create(['role' => 'end_user']);
        $expert = User::factory()->create(['role' => 'expert']);
        $createLayer = function (User $owner, string $name, string $status = 'ready') {
            $layer = DroneImageryLayer::create([
                'user_id' => $owner->id,
                'name' => $name,
                'status' => $status,
                'source_type' => 'single',
                'bounds' => ['west' => 124.0, 'south' => 9.9, 'east' => 124.1, 'north' => 10.0],
                'zoom_min' => 12,
                'zoom_max' => 20,
            ]);
            $layer->update(['tiles_path' => "drone-imagery/tiles/{$layer->id}"]);

            return $layer->fresh();
        };
        $residentLayer = $createLayer($resident, 'Resident survey', 'processing');
        $expertLayer = $createLayer($expert, 'Expert survey');
        $otherResidentLayer = $createLayer($otherResident, 'Private resident survey');
        $processingExpertLayer = $createLayer($expert, 'Processing expert survey', 'processing');
        Storage::disk('local')->put("{$expertLayer->tiles_path}/12/3458/1933.png", 'expert-tile');

        $response = $this->actingAs($resident)->get(route('drone-imagery.index'));
        $response->assertOk()
            ->assertJsonFragment([
                'id' => $expertLayer->id,
                'name' => 'Expert survey',
                'uploaded_by' => $expert->name,
            ]);
        $visibleLayerIds = collect($response->json('layers'))->pluck('id')->all();
        $this->assertContains($residentLayer->id, $visibleLayerIds);
        $this->assertContains($expertLayer->id, $visibleLayerIds);
        $this->assertNotContains($otherResidentLayer->id, $visibleLayerIds);
        $this->assertNotContains($processingExpertLayer->id, $visibleLayerIds);

        $this->get(route('drone-imagery.tile', [$expertLayer->id, 12, 3458, 1933]))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
        $this->get(route('drone-imagery.tile', [$otherResidentLayer->id, 12, 3458, 1933]))
            ->assertNotFound();
        $this->get(route('drone-imagery.tile', [$processingExpertLayer->id, 12, 3458, 1933]))
            ->assertNotFound();
    }

    public function test_resident_can_hide_shared_expert_layer_without_affecting_other_viewers(): void
    {
        Storage::fake('local');
        $resident = User::factory()->create(['role' => 'end_user']);
        $anotherResident = User::factory()->create(['role' => 'end_user']);
        $expert = User::factory()->create(['role' => 'expert']);
        $layer = DroneImageryLayer::create([
            'user_id' => $expert->id,
            'name' => 'Shared coastal survey',
            'status' => 'ready',
            'source_type' => 'single',
            'bounds' => ['west' => 124.0, 'south' => 9.9, 'east' => 124.1, 'north' => 10.0],
            'zoom_min' => 12,
            'zoom_max' => 20,
        ]);
        $layer->update(['tiles_path' => "drone-imagery/tiles/{$layer->id}"]);
        Storage::disk('local')->put("{$layer->tiles_path}/12/3458/1933.png", 'expert-tile');

        $this->actingAs($resident)
            ->delete(route('drone-imagery.destroy', $layer->id))
            ->assertOk()
            ->assertJsonPath('action', 'hidden');

        $this->actingAs($resident)
            ->get(route('drone-imagery.index'))
            ->assertOk()
            ->assertJsonMissing(['id' => $layer->id]);
        $this->actingAs($resident)
            ->get(route('drone-imagery.tile', [$layer->id, 12, 3458, 1933]))
            ->assertNotFound();

        $this->actingAs($anotherResident)
            ->get(route('drone-imagery.index'))
            ->assertOk()
            ->assertJsonFragment(['id' => $layer->id, 'name' => 'Shared coastal survey']);
        $this->actingAs($anotherResident)
            ->get(route('drone-imagery.tile', [$layer->id, 12, 3458, 1933]))
            ->assertOk();
        $this->actingAs($expert)
            ->get(route('drone-imagery.index'))
            ->assertOk()
            ->assertJsonFragment(['id' => $layer->id, 'name' => 'Shared coastal survey']);
    }

    public function test_owner_can_delete_tiff_layer_and_files_only_after_processing(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create(['role' => 'expert']);
        $processingLayer = DroneImageryLayer::create([
            'user_id' => $owner->id,
            'name' => 'Processing survey',
            'status' => 'processing',
            'source_type' => 'single',
        ]);

        $this->actingAs($owner)
            ->delete(route('drone-imagery.destroy', $processingLayer->id))
            ->assertStatus(409);
        $this->assertDatabaseHas('drone_imagery_layers', ['id' => $processingLayer->id]);

        $layer = DroneImageryLayer::create([
            'user_id' => $owner->id,
            'name' => 'Completed survey',
            'status' => 'ready',
            'source_type' => 'single',
        ]);
        $layer->files()->create([
            'original_name' => 'survey.tif',
            'stored_path' => "drone-imagery/uploads/{$layer->id}/survey.tif",
            'mime_type' => 'image/tiff',
            'file_size' => 10,
            'sort_order' => 0,
        ]);
        foreach ([
            "drone-imagery/uploads/{$layer->id}/survey.tif",
            "drone-imagery/tiles/{$layer->id}/12/3458/1933.png",
            "drone-imagery/processed/{$layer->id}/mosaic.tif",
        ] as $path) {
            Storage::disk('local')->put($path, 'imagery');
        }

        $this->actingAs($owner)
            ->delete(route('drone-imagery.destroy', $layer->id))
            ->assertOk()
            ->assertJsonPath('action', 'deleted');

        $this->assertDatabaseMissing('drone_imagery_layers', ['id' => $layer->id]);
        Storage::disk('local')->assertMissing("drone-imagery/uploads/{$layer->id}/survey.tif");
        Storage::disk('local')->assertMissing("drone-imagery/tiles/{$layer->id}/12/3458/1933.png");
        Storage::disk('local')->assertMissing("drone-imagery/processed/{$layer->id}/mosaic.tif");
    }
}
