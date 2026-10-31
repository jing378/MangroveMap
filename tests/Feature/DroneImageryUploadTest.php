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
}
