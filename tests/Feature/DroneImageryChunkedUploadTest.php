<?php

namespace Tests\Feature;

use App\Http\Controllers\DroneImageryUploadController;
use App\Jobs\ProcessDroneImagery;
use App\Models\DroneImageryLayer;
use App\Models\TileSet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DroneImageryChunkedUploadTest extends TestCase
{
    use RefreshDatabase;

    private const CHUNK = 1024 * 1024; // config gdal.upload_chunk_mb = 1

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Queue::fake();
        config(['gdal.upload_chunk_mb' => 1]);
    }

    private function startUpload(User $user, array $files, ?string $name = null): string
    {
        return $this->actingAs($user)->postJson(route('drone-imagery.uploads.start'), [
            'name' => $name,
            'files' => array_map(fn ($name, $content) => ['name' => $name, 'size' => strlen($content)], array_keys($files), $files),
        ])->assertCreated()->assertJsonPath('chunk_size', self::CHUNK)->json('upload_id');
    }

    private function putChunk(string $id, int $file, int $chunk, string $body)
    {
        return $this->call('PUT', "/drone-imagery/uploads/{$id}/files/{$file}/chunks/{$chunk}", [], [], [],
            ['CONTENT_TYPE' => 'application/octet-stream', 'HTTP_ACCEPT' => 'application/json'], $body);
    }

    public function test_chunks_sent_out_of_order_are_assembled_into_the_layer_files(): void
    {
        $user = User::factory()->create();
        $north = random_bytes(self::CHUNK * 2 + 123);
        $south = random_bytes(500);
        $id = $this->startUpload($user, ['north.tif' => $north, 'south.TIFF' => $south], 'Survey B');

        $this->putChunk($id, 0, 2, substr($north, self::CHUNK * 2))->assertOk();
        $this->putChunk($id, 1, 0, $south)->assertOk();
        $this->putChunk($id, 0, 0, substr($north, 0, self::CHUNK))->assertOk();
        $this->putChunk($id, 0, 1, substr($north, self::CHUNK, self::CHUNK))->assertOk();

        $this->postJson("/drone-imagery/uploads/{$id}/complete")->assertAccepted()
            ->assertJsonPath('name', 'Survey B')
            ->assertJsonPath('source_type', 'batch')
            ->assertJsonPath('file_count', 2);

        $layer = DroneImageryLayer::with('files')->sole();
        $this->assertSame($user->id, $layer->user_id);
        $this->assertSame(['north.tif', 'south.TIFF'], $layer->files->pluck('original_name')->all());
        $this->assertSame($north, Storage::disk('local')->get($layer->files[0]->stored_path));
        $this->assertSame($south, Storage::disk('local')->get($layer->files[1]->stored_path));
        $this->assertStringEndsWith('.tiff', $layer->files[1]->stored_path);
        Queue::assertPushedOn('drone-imagery', ProcessDroneImagery::class);
        Storage::disk('local')->assertMissing(DroneImageryUploadController::INCOMING_DIRECTORY."/{$id}");
    }

    public function test_many_chunks_do_not_use_up_the_complete_or_other_rate_limits(): void
    {
        $user = User::factory()->create();
        $data = random_bytes(500);
        $id = $this->startUpload($user, ['survey.tif' => $data]);

        // More chunk requests in a minute than complete (20) or the notification poll (30) allow:
        // each route counts on its own, so neither is refused with 429.
        foreach (range(1, 35) as $ignored) {
            $this->putChunk($id, 0, 0, $data)->assertOk();
        }

        $this->getJson(route('notifications.poll'))->assertOk();
        $this->postJson("/drone-imagery/uploads/{$id}/complete")->assertAccepted();
    }

    public function test_status_lists_received_chunks_for_resuming(): void
    {
        $user = User::factory()->create();
        $data = random_bytes(self::CHUNK * 3);
        $id = $this->startUpload($user, ['survey.tif' => $data]);

        $this->putChunk($id, 0, 1, substr($data, self::CHUNK, self::CHUNK))->assertOk();

        $this->getJson("/drone-imagery/uploads/{$id}")->assertOk()->assertJsonPath('received', [[1]]);
    }

    public function test_complete_refuses_missing_chunks(): void
    {
        $user = User::factory()->create();
        $data = random_bytes(self::CHUNK + 10);
        $id = $this->startUpload($user, ['survey.tif' => $data]);
        $this->putChunk($id, 0, 0, substr($data, 0, self::CHUNK))->assertOk();

        $this->postJson("/drone-imagery/uploads/{$id}/complete")->assertUnprocessable()->assertJsonValidationErrors('files');

        $this->assertDatabaseCount('drone_imagery_layers', 0);
        Queue::assertNothingPushed();
    }

    public function test_chunk_with_wrong_length_is_not_marked_received(): void
    {
        $user = User::factory()->create();
        $id = $this->startUpload($user, ['survey.tif' => random_bytes(self::CHUNK * 2)]);

        $this->putChunk($id, 0, 0, random_bytes(100))->assertUnprocessable();
        $this->putChunk($id, 0, 0, random_bytes(self::CHUNK + 1))->assertUnprocessable();
        $this->putChunk($id, 0, 5, random_bytes(100))->assertNotFound();

        $this->getJson("/drone-imagery/uploads/{$id}")->assertJsonPath('received', [[]]);
    }

    public function test_start_validates_extension_size_and_batch_limit(): void
    {
        $user = User::factory()->create();
        config(['gdal.max_upload_files' => 2]);

        $this->actingAs($user)->postJson(route('drone-imagery.uploads.start'), [
            'files' => [['name' => 'notes.txt', 'size' => 10]],
        ])->assertUnprocessable()->assertJsonValidationErrors('files.0.name');

        $this->postJson(route('drone-imagery.uploads.start'), [
            'files' => [['name' => 'huge.tif', 'size' => DroneImageryUploadController::MAX_FILE_BYTES + 1]],
        ])->assertUnprocessable()->assertJsonValidationErrors('files.0.size');

        $this->postJson(route('drone-imagery.uploads.start'), [
            'files' => array_fill(0, 3, ['name' => 'a.tif', 'size' => 10]),
        ])->assertUnprocessable()->assertJsonValidationErrors('files');
    }

    public function test_drone_photos_are_stored_with_their_location_files_next_to_them(): void
    {
        $user = User::factory()->create();
        $photo = random_bytes(300);
        $worldFile = "0.1\n0\n0\n-0.1\n720500\n1147650\n";
        $tile = random_bytes(200);
        $id = $this->startUpload($user, [
            'DJI_0001_V.JPG' => $photo,
            'tile.jgw' => $worldFile,
            'tile.jpg' => $tile,
        ], 'Photo survey');

        $this->putChunk($id, 0, 0, $photo)->assertOk();
        $this->putChunk($id, 1, 0, $worldFile)->assertOk();
        $this->putChunk($id, 2, 0, $tile)->assertOk();

        // Location files are not images of the layer: two pictures, one batch.
        $this->postJson("/drone-imagery/uploads/{$id}/complete")->assertAccepted()
            ->assertJsonPath('source_type', 'batch')
            ->assertJsonPath('file_count', 2);

        $layer = DroneImageryLayer::with('files')->sole();
        $this->assertSame(['DJI_0001_V.JPG', 'tile.jpg'], $layer->files->pluck('original_name')->all());
        $this->assertSame(['image/jpeg', 'image/jpeg'], $layer->files->pluck('mime_type')->all());
        $tilePath = $layer->files[1]->stored_path;
        $this->assertStringEndsWith('.jpg', $tilePath);
        // GDAL finds the world file because it has the stored picture's name.
        $this->assertSame($worldFile, Storage::disk('local')->get(substr($tilePath, 0, -4).'.jgw'));
        Queue::assertPushedOn('drone-imagery', ProcessDroneImagery::class);
    }

    public function test_start_refuses_geotiffs_mixed_with_photos_and_location_files_without_their_photo(): void
    {
        $user = User::factory()->create();
        $start = fn (array $names) => $this->actingAs($user)->postJson(route('drone-imagery.uploads.start'), [
            'files' => array_map(fn (string $name) => ['name' => $name, 'size' => 10], $names),
        ]);

        $start(['ortho.tif', 'DJI_0001_V.JPG'])->assertUnprocessable()
            ->assertJsonPath('errors.files.0', 'Upload GeoTIFFs and photos separately: one upload is either GeoTIFF files or photos.');
        $start(['DJI_0001_V.JPG', 'other.jgw'])->assertUnprocessable()->assertJsonValidationErrors('files');

        config(['gdal.max_upload_photos' => 2]);
        $start(['a.jpg', 'b.jpg', 'c.jpg'])->assertUnprocessable()->assertJsonValidationErrors('files');
        $start(['a.jpg', 'b.png', 'b.pgw'])->assertCreated();
    }

    public function test_tile_explorer_uploads_still_take_geotiffs_only(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('drone-imagery.uploads.start'), [
            'purpose' => 'tiles',
            'tile_size' => TileSet::TILE_SIZES[0],
            'files' => [['name' => 'DJI_0001_V.JPG', 'size' => 10]],
        ])->assertUnprocessable()->assertJsonValidationErrors('files.0.name');
    }

    public function test_other_users_cannot_touch_an_upload_session(): void
    {
        $owner = User::factory()->create();
        $id = $this->startUpload($owner, ['survey.tif' => 'abc']);

        $this->actingAs(User::factory()->create());
        $this->getJson("/drone-imagery/uploads/{$id}")->assertNotFound();
        $this->putChunk($id, 0, 0, 'abc')->assertNotFound();
        $this->postJson("/drone-imagery/uploads/{$id}/complete")->assertNotFound();
        $this->deleteJson("/drone-imagery/uploads/{$id}")->assertNotFound();
    }

    public function test_cancel_and_prune_remove_sessions(): void
    {
        $user = User::factory()->create();
        $cancelled = $this->startUpload($user, ['a.tif' => 'abc']);
        $this->deleteJson("/drone-imagery/uploads/{$cancelled}")->assertOk();
        Storage::disk('local')->assertMissing(DroneImageryUploadController::INCOMING_DIRECTORY."/{$cancelled}");

        $stale = $this->startUpload($user, ['b.tif' => 'abc']);
        $fresh = $this->startUpload($user, ['c.tif' => 'abc']);
        touch(Storage::disk('local')->path(DroneImageryUploadController::INCOMING_DIRECTORY."/{$stale}/manifest.json"), now()->subDays(2)->getTimestamp());

        $this->artisan('drone-imagery:prune-uploads')->assertSuccessful();

        Storage::disk('local')->assertMissing(DroneImageryUploadController::INCOMING_DIRECTORY."/{$stale}");
        Storage::disk('local')->assertExists(DroneImageryUploadController::INCOMING_DIRECTORY."/{$fresh}/manifest.json");
    }
}
