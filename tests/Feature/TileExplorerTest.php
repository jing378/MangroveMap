<?php

namespace Tests\Feature;

use App\Http\Controllers\TileExplorerController;
use App\Jobs\GenerateTiles;
use App\Models\TileSet;
use App\Models\TileSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TileExplorerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Queue::fake();
        config(['gdal.upload_chunk_mb' => 1]);
    }

    private function source(User $owner, string $name = 'Brgy San Isidro March'): TileSource
    {
        $source = TileSource::create([
            'user_id' => $owner->id,
            'name' => $name,
            'original_name' => 'survey.tif',
            'stored_path' => '',
            'file_size' => 6,
            'crs' => 'EPSG:32651',
        ]);
        $path = $source->directory().'/source.tif';
        Storage::disk('local')->put($path, 'geotif');
        $source->update(['stored_path' => $path]);

        return $source;
    }

    /** A ready tile set with two tiles (r000_c000 mangrove, r000_c001 not) and their files. */
    private function readySet(TileSource $source): TileSet
    {
        $set = $source->tileSets()->create([
            'tile_size' => 640,
            'status' => 'ready',
            'tile_count' => 2,
            'mangrove_tile_count' => 1,
            'columns' => 2,
            'rows' => 1,
            'generated_at' => now(),
        ]);
        $disk = Storage::disk('local');
        $tiles = [];
        foreach (['r000_c000' => 62.5, 'r000_c001' => 0.0] as $id => $mangrove) {
            $disk->put("{$set->directory()}/tif/{$id}.tif", "tif-{$id}");
            $disk->put("{$set->directory()}/jpg/{$id}.jpg", "jpg-{$id}");
            $disk->put("{$set->directory()}/jpg/{$id}.wld", "wld-{$id}");
            $disk->put("{$set->directory()}/thumb/{$id}.jpg", "thumb-{$id}");
            $tiles[] = [
                'id' => $id, 'name' => "brgy-san-isidro-march_{$id}", 'row' => 0, 'col' => (int) substr($id, -1),
                'valid' => 100.0, 'mangrove' => $mangrove, 'bounds' => [125.1, 10.1, 125.2, 10.2], 'outline' => '',
            ];
        }
        $disk->put($set->manifestPath(), json_encode([
            'tile_size' => 640, 'columns' => 2, 'rows' => 1, 'crs' => 'EPSG:32651', 'pixel_size' => 0.05,
            'has_areas' => true, 'tiles' => $tiles,
        ]));

        return $set;
    }

    private function putChunk(string $id, int $chunk, string $body)
    {
        return $this->call('PUT', "/drone-imagery/uploads/{$id}/files/0/chunks/{$chunk}", [], [], [],
            ['CONTENT_TYPE' => 'application/octet-stream', 'HTTP_ACCEPT' => 'application/json'], $body);
    }

    public function test_sidebar_links_the_page_and_it_lists_only_the_viewers_uploads(): void
    {
        $owner = User::factory()->create(['role' => 'expert']);
        $mine = $this->source($owner, 'My mangrove survey');
        $this->source(User::factory()->create(), 'Someone else');

        $this->actingAs($owner)->get(route('tile-explorer.page'))
            ->assertOk()
            ->assertSee('Tile Explorer')
            ->assertSee('href="'.route('tile-explorer.page').'"', false)
            ->assertSee('id="txUploadForm"', false)
            ->assertSee('"id":'.$mine->id, false)
            ->assertSee('My mangrove survey')
            ->assertDontSee('Someone else');
    }

    public function test_chunked_tiles_upload_creates_a_source_and_queues_tiles_without_a_drone_layer(): void
    {
        $owner = User::factory()->create();
        $data = random_bytes(1024 * 1024 + 50);

        $id = $this->actingAs($owner)->postJson(route('drone-imagery.uploads.start'), [
            'purpose' => 'tiles',
            'tile_size' => 512,
            'name' => 'Coastal strip',
            'files' => [['name' => 'coastal.TIFF', 'size' => strlen($data)]],
        ])->assertCreated()->json('upload_id');
        $this->putChunk($id, 1, substr($data, 1024 * 1024))->assertOk();
        $this->putChunk($id, 0, substr($data, 0, 1024 * 1024))->assertOk();

        $this->postJson("/drone-imagery/uploads/{$id}/complete")
            ->assertAccepted()
            ->assertJsonPath('name', 'Coastal strip')
            ->assertJsonPath('original_name', 'coastal.TIFF')
            ->assertJsonPath('tile_sets.0.tile_size', 512)
            ->assertJsonPath('tile_sets.0.status', 'queued');

        $source = TileSource::sole();
        $this->assertSame($owner->id, $source->user_id);
        $this->assertSame("tile-explorer/{$source->id}/source.tiff", $source->stored_path);
        $this->assertSame($data, Storage::disk('local')->get($source->stored_path));
        $this->assertDatabaseCount('drone_imagery_layers', 0);
        Queue::assertPushedOn('drone-imagery', GenerateTiles::class);
    }

    public function test_tiles_upload_takes_one_file_and_needs_a_valid_tile_size(): void
    {
        $this->actingAs(User::factory()->create());

        $this->postJson(route('drone-imagery.uploads.start'), [
            'purpose' => 'tiles',
            'tile_size' => 640,
            'files' => [['name' => 'a.tif', 'size' => 10], ['name' => 'b.tif', 'size' => 10]],
        ])->assertUnprocessable()->assertJsonValidationErrors('files');

        $this->postJson(route('drone-imagery.uploads.start'), [
            'purpose' => 'tiles',
            'tile_size' => 300,
            'files' => [['name' => 'a.tif', 'size' => 10]],
        ])->assertUnprocessable()->assertJsonValidationErrors('tile_size');
    }

    public function test_another_tile_size_queues_once_and_ready_sets_are_reused(): void
    {
        $owner = User::factory()->create();
        $source = $this->source($owner);
        $this->readySet($source);

        $this->actingAs($owner)->postJson(route('tile-explorer.sets.store', $source->id), ['tile_size' => 640])
            ->assertOk()->assertJsonPath('status', 'ready');
        Queue::assertNothingPushed();

        $this->postJson(route('tile-explorer.sets.store', $source->id), ['tile_size' => 1024])
            ->assertAccepted()->assertJsonPath('status', 'queued');
        $this->postJson(route('tile-explorer.sets.store', $source->id), ['tile_size' => 1024])
            ->assertOk();
        Queue::assertPushed(GenerateTiles::class, 1);

        $this->postJson(route('tile-explorer.sets.store', $source->id), ['tile_size' => 640, 'force' => true])
            ->assertAccepted();
        Queue::assertPushed(GenerateTiles::class, 2);

        $this->actingAs(User::factory()->create())
            ->postJson(route('tile-explorer.sets.store', $source->id), ['tile_size' => 512])
            ->assertNotFound();
    }

    public function test_ready_set_lists_tiles_and_serves_images_to_the_owner_only(): void
    {
        $owner = User::factory()->create();
        $set = $this->readySet($this->source($owner));

        $this->actingAs($owner)->getJson(route('tile-explorer.sets.show', $set->id))
            ->assertOk()
            ->assertJsonPath('status', 'ready')
            ->assertJsonPath('crs', 'EPSG:32651')
            ->assertJsonPath('source.id', $set->tile_source_id)
            ->assertJsonCount(2, 'tiles');

        $this->get(route('tile-explorer.tiles.image', [$set->id, 'r000_c000', 'thumb']))->assertOk();
        $this->get(route('tile-explorer.tiles.image', [$set->id, 'r000_c001', 'full']))->assertOk();
        $this->get(route('tile-explorer.tiles.image', [$set->id, 'r009_c009', 'thumb']))->assertNotFound();
        $this->get("/tile-explorer/sets/{$set->id}/tiles/..%2Ftiles/thumb")->assertNotFound();

        $this->actingAs(User::factory()->create(['role' => 'expert']));
        $this->getJson(route('tile-explorer.sets.show', $set->id))->assertNotFound();
        $this->get(route('tile-explorer.tiles.image', [$set->id, 'r000_c000', 'thumb']))->assertNotFound();
    }

    public function test_download_zips_only_the_selected_tiles_with_an_index(): void
    {
        $owner = User::factory()->create();
        $set = $this->readySet($this->source($owner));

        $response = $this->actingAs($owner)->post(route('tile-explorer.sets.download', $set->id), [
            'ids' => 'r000_c000',
            'format' => 'both',
            'token' => 'abc123def456',
        ]);

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/zip')
            ->assertCookie(TileExplorerController::DOWNLOAD_COOKIE, 'abc123def456', false);
        $this->assertStringContainsString('brgy-san-isidro-march_640px_1-tiles.zip', $response->headers->get('Content-Disposition'));

        $zip = $response->streamedContent();
        $this->assertStringStartsWith("PK\x03\x04", $zip);
        foreach ([
            'brgy-san-isidro-march_640px/geotiff/brgy-san-isidro-march_r000_c000.tif',
            'brgy-san-isidro-march_640px/jpeg/brgy-san-isidro-march_r000_c000.jpg',
            'brgy-san-isidro-march_640px/jpeg/brgy-san-isidro-march_r000_c000.wld',
            'brgy-san-isidro-march_640px/tiles.csv',
            'brgy-san-isidro-march_640px/tiles.geojson',
            'brgy-san-isidro-march_640px/README.txt',
            'tif-r000_c000',
        ] as $expected) {
            $this->assertStringContainsString($expected, $zip);
        }
        $this->assertStringNotContainsString('r000_c001', $zip);
    }

    public function test_download_in_one_format_and_rejects_bad_requests(): void
    {
        $owner = User::factory()->create();
        $set = $this->readySet($this->source($owner));

        $zip = $this->actingAs($owner)->post(route('tile-explorer.sets.download', $set->id), [
            'ids' => ['r000_c000', 'r000_c001'],
            'format' => 'jpg',
        ])->assertOk()->streamedContent();
        $this->assertStringContainsString('jpeg/brgy-san-isidro-march_r000_c001.jpg', $zip);
        $this->assertStringNotContainsString('.tif', $zip);

        $this->postJson(route('tile-explorer.sets.download', $set->id), ['ids' => '../x', 'format' => 'jpg'])
            ->assertUnprocessable();
        $this->postJson(route('tile-explorer.sets.download', $set->id), ['ids' => 'r000_c000', 'format' => 'png'])
            ->assertUnprocessable();

        $this->actingAs(User::factory()->create())
            ->postJson(route('tile-explorer.sets.download', $set->id), ['ids' => 'r000_c000', 'format' => 'jpg'])
            ->assertNotFound();
    }

    public function test_owner_can_delete_an_upload_with_its_tiles_unless_it_is_being_tiled(): void
    {
        $owner = User::factory()->create();
        $source = $this->source($owner);
        $set = $this->readySet($source);

        $this->actingAs(User::factory()->create())->deleteJson(route('tile-explorer.sources.destroy', $source->id))->assertNotFound();

        $set->update(['status' => 'processing']);
        $this->actingAs($owner)->deleteJson(route('tile-explorer.sources.destroy', $source->id))->assertConflict();

        $set->update(['status' => 'ready']);
        $this->deleteJson(route('tile-explorer.sources.destroy', $source->id))->assertOk();
        $this->assertDatabaseCount('tile_sources', 0);
        $this->assertDatabaseCount('tile_sets', 0);
        Storage::disk('local')->assertMissing($source->directory());
    }

    public function test_crs_name_prefers_the_epsg_code(): void
    {
        $this->assertSame('EPSG:32651', GenerateTiles::crsName(['stac' => ['proj:epsg' => 32651]]));
        $this->assertSame('EPSG:3123', GenerateTiles::crsName(['coordinateSystem' => ['wkt' => "PROJCRS[\"PRS92 / Philippines zone III\",\n    ID[\"EPSG\",3123]]"]]));
        $this->assertSame('Local grid', GenerateTiles::crsName(['coordinateSystem' => ['wkt' => 'PROJCRS["Local grid",BASEGEOGCRS[]]']]));
    }
}
