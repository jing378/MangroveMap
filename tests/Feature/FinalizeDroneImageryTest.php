<?php

namespace Tests\Feature;

use App\Jobs\FinalizeDroneImagery;
use App\Jobs\ProcessDroneImagery;
use App\Jobs\SegmentDroneImagery;
use App\Models\Delineation;
use App\Models\DroneImageryLayer;
use App\Models\User;
use App\Services\GdalCommandRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Runs real GDAL (QGIS install) to check that review edits are applied to the tiled imagery. */
class FinalizeDroneImageryTest extends TestCase
{
    use RefreshDatabase;

    private const WEST = 124.0;

    private const EAST = 124.01;

    private const NORTH = 10.0;

    private const SOUTH = 9.99;

    protected function setUp(): void
    {
        parent::setUp();

        $environmentScript = config('gdal.qgis_env');
        if (! is_string($environmentScript) || ! is_file($environmentScript)) {
            $this->markTestSkipped('QGIS GDAL is not installed in this test environment.');
        }

        Storage::fake('local');
        config(['gdal.tile_zoom' => '12-14', 'ml.drone.auto_segment' => false]);
    }

    public function test_confirming_without_edits_tiles_the_original_mosaic(): void
    {
        $layer = $this->preparedLayer();

        $this->finalize($layer);

        $this->assertSame('ready', $layer->status, $layer->error_message ?? '');
        $this->assertNull($layer->final_mosaic_path);
        $this->assertSame(DroneImageryLayer::EMPTY_EDITS, $layer->applied_edits);
        $this->assertNotEmpty(File::allFiles(Storage::disk('local')->path($layer->tiles_path)));
        $this->assertNotNull($layer->reviewed_at);
    }

    public function test_removed_areas_become_transparent(): void
    {
        $layer = $this->preparedLayer();
        $layer->update(['edits' => $this->edits('remove', [$this->westHalf()])]);

        $this->finalize($layer);

        $this->assertSame('ready', $layer->status, $layer->error_message ?? '');
        $this->assertSame("drone-imagery/processed/{$layer->id}/final_masked.tif", $layer->final_mosaic_path);
        $this->assertSame(0, $this->alphaAt($layer, 124.002, 9.995));
        $this->assertSame(255, $this->alphaAt($layer, 124.008, 9.995));
        // Removing areas keeps the full extent; the original mosaic is untouched for later re-edits.
        $this->assertEqualsWithDelta(self::WEST, $layer->bounds['west'], 0.0005);
        Storage::disk('local')->assertExists($layer->mosaic_path);
        $this->assertFalse($layer->hasPendingEdits());
    }

    public function test_keep_only_crops_the_imagery_to_the_drawn_areas(): void
    {
        $layer = $this->preparedLayer();
        $eastHalf = [[124.005, 10.0], [124.01, 10.0], [124.01, 9.99], [124.005, 9.99]];
        $layer->update(['edits' => $this->edits('keep', [$eastHalf])]);

        $this->finalize($layer);

        $this->assertSame('ready', $layer->status, $layer->error_message ?? '');
        $this->assertGreaterThan(124.004, $layer->bounds['west']);
        $this->assertEqualsWithDelta(self::EAST, $layer->bounds['east'], 0.0005);
        $this->assertSame(255, $this->alphaAt($layer, 124.008, 9.995));
    }

    public function test_position_offset_shifts_the_imagery_bounds(): void
    {
        $layer = $this->preparedLayer();
        $before = $layer->bounds;
        $layer->update(['edits' => [...DroneImageryLayer::EMPTY_EDITS, 'offset' => ['east_m' => 20, 'north_m' => -10]]]);

        $this->finalize($layer);

        $this->assertSame('ready', $layer->status, $layer->error_message ?? '');
        $metresPerDegree = 111320;
        $expectedEast = 20 / ($metresPerDegree * cos(deg2rad(9.995)));
        $this->assertEqualsWithDelta($before['west'] + $expectedEast, $layer->bounds['west'], 0.00002);
        $this->assertEqualsWithDelta($before['north'] - 10 / $metresPerDegree, $layer->bounds['north'], 0.00002);
        // The preview stays at the original position; the editor applies the offset on top of it.
        $this->assertSame($before, $layer->preview_bounds);
    }

    public function test_preparing_queues_ai_detection_for_the_review(): void
    {
        Queue::fake();
        config(['ml.drone.auto_segment' => true]);

        $layer = $this->preparedLayer();

        $this->assertSame('queued', $layer->segmentation_status);
        Queue::assertPushedOn('drone-imagery', SegmentDroneImagery::class, fn ($job) => $job->layerId === $layer->id);
    }

    public function test_review_ai_result_is_kept_when_the_imagery_is_confirmed_unchanged(): void
    {
        Queue::fake();
        config(['ml.drone.auto_segment' => true]);
        $layer = $this->preparedLayer();
        $layer->update($this->readyAiResult($layer));

        $this->finalize($layer);

        $this->assertSame('ready', $layer->status, $layer->error_message ?? '');
        $this->assertSame('ready', $layer->segmentation_status);
        Storage::disk('local')->assertExists("drone-imagery/processed/{$layer->id}/ai_extent.json");
        Queue::assertPushed(SegmentDroneImagery::class, 1); // only the one queued while preparing
    }

    public function test_submitted_mangrove_areas_become_a_pending_delineation_once_tiled(): void
    {
        Queue::fake();
        Notification::fake();
        $resident = User::factory()->create(['role' => 'end_user']);
        $layer = $this->preparedLayer();
        $layer->update([
            ...$this->readyAiResult($layer),
            'user_id' => $resident->id,
            'delineation_details' => ['name' => 'Submitted survey', 'genera' => ['Rhizophora'], 'notes' => null],
        ]);
        $ring = [[self::SOUTH, self::WEST], [self::SOUTH, self::EAST], [self::NORTH, self::EAST]];
        Storage::disk('local')->put($layer->segmentation_path, json_encode([
            'features' => [['type' => 'area', 'coords' => $ring]],
            'stats' => [],
        ]));

        $this->finalize($layer);

        $this->assertSame('ready', $layer->status, $layer->error_message ?? '');
        $delineation = Delineation::sole();
        $this->assertSame($layer->id, $delineation->drone_imagery_layer_id);
        $this->assertSame('Submitted survey', $delineation->name);
        $this->assertFalse((bool) $delineation->is_approved);
        $this->assertNull($layer->delineation_details);
        $this->assertFalse($layer->isPublished());
    }

    public function test_ai_delineation_is_kept_when_the_imagery_is_edited(): void
    {
        Queue::fake();
        config(['ml.drone.auto_segment' => true]);
        $layer = $this->preparedLayer();
        $layer->update([...$this->readyAiResult($layer), 'edits' => $this->edits('remove', [$this->westHalf()])]);

        $this->finalize($layer);

        $this->assertSame('ready', $layer->status, $layer->error_message ?? '');
        $this->assertSame('ready', $layer->segmentation_status);
        Storage::disk('local')->assertExists("drone-imagery/processed/{$layer->id}/ai_extent.json");
        Queue::assertPushed(SegmentDroneImagery::class, 1); // only the one queued while preparing
    }

    public function test_ai_delineation_moves_with_the_imagery_offset(): void
    {
        $layer = $this->preparedLayer();
        $path = "drone-imagery/processed/{$layer->id}/ai_extent.json";
        Storage::disk('local')->put($path, json_encode([
            'features' => [['type' => 'area', 'coords' => [[9.999, 124.001], [9.999, 124.004], [9.995, 124.004]]]],
            'stats' => [],
        ]));
        $layer->update([
            'segmentation_status' => 'ready',
            'segmentation_path' => $path,
            'segmentation_stats' => ['mangrove_area_ha' => 1, 'offset' => ['east_m' => 0, 'north_m' => 0]],
            'edits' => [...DroneImageryLayer::EMPTY_EDITS, 'offset' => ['east_m' => 0, 'north_m' => 11.132]],
        ]);

        $this->finalize($layer);

        $this->assertSame('ready', $layer->status, $layer->error_message ?? '');
        $this->assertSame(11.132, (float) $layer->segmentation_stats['offset']['north_m']);
        $moved = json_decode(Storage::disk('local')->get($path), true)['features'][0]['coords'][0];
        $this->assertEqualsWithDelta(9.9991, $moved[0], 0.000002); // 11.132 m north = 0.0001 degree
        $this->assertEqualsWithDelta(124.001, $moved[1], 0.0000001);
    }

    public function test_ai_detection_is_queued_when_there_is_no_ai_result_yet(): void
    {
        Queue::fake();
        $layer = $this->preparedLayer(); // auto_segment off while preparing: no AI result
        config(['ml.drone.auto_segment' => true]);

        $this->finalize($layer);

        $this->assertSame('ready', $layer->status, $layer->error_message ?? '');
        $this->assertSame('queued', $layer->segmentation_status);
        Queue::assertPushed(SegmentDroneImagery::class, 1);
    }

    public function test_crop_to_mangrove_areas_keeps_only_those_areas(): void
    {
        $layer = $this->preparedLayer();
        $eastHalf = [[124.005, 10.0], [124.01, 10.0], [124.01, 9.99], [124.005, 9.99]];
        $layer->update(['edits' => $this->edits('keep_ai', [$eastHalf])]);

        $this->finalize($layer);

        $this->assertSame('ready', $layer->status, $layer->error_message ?? '');
        $this->assertGreaterThan(124.004, $layer->bounds['west']);
    }

    public function test_corrected_mangrove_areas_survive_confirming_edited_imagery(): void
    {
        Queue::fake();
        config(['ml.drone.auto_segment' => true]);
        $layer = $this->preparedLayer();
        $layer->update([
            ...$this->readyAiResult($layer),
            'segmentation_stats' => ['mangrove_area_ha' => 0.4, 'edited' => true],
            'edits' => $this->edits('remove', [$this->westHalf()]),
        ]);

        $this->finalize($layer);

        $this->assertSame('ready', $layer->status, $layer->error_message ?? '');
        $this->assertTrue($layer->isSegmentationEdited());
        Storage::disk('local')->assertExists("drone-imagery/processed/{$layer->id}/ai_extent.json");
        Queue::assertPushed(SegmentDroneImagery::class, 1); // only the one queued while preparing
    }

    public function test_removing_everything_fails_but_keeps_the_layer_editable(): void
    {
        $layer = $this->preparedLayer();
        $everything = [[123.99, 10.01], [124.02, 10.01], [124.02, 9.98], [123.99, 9.98]];
        $layer->update(['edits' => $this->edits('remove', [$everything])]);

        $this->finalize($layer);

        $this->assertSame('failed', $layer->status);
        $this->assertStringContainsString('without generating any map tiles', $layer->error_message ?? '');
        $this->assertTrue($layer->isReviewable());
        Storage::disk('local')->assertExists($layer->preview_path);
        Storage::disk('local')->assertExists($layer->mosaic_path);
        $this->assertFalse(Storage::disk('local')->exists("drone-imagery/tiles/{$layer->id}"));
    }

    private function preparedLayer(): DroneImageryLayer
    {
        $localDisk = Storage::disk('local');
        $sourceDirectory = storage_path('app/private/drone-imagery-test-inputs/'.uniqid('', true));
        File::ensureDirectoryExists($sourceDirectory);

        try {
            $sourcePath = $sourceDirectory.DIRECTORY_SEPARATOR.'survey.tif';
            app(GdalCommandRunner::class)->run([
                'gdal_create', '-of', 'GTiff', '-outsize', '64', '64', '-bands', '3', '-burn', '100',
                '-a_srs', 'EPSG:4326',
                '-a_ullr', (string) self::WEST, (string) self::NORTH, (string) self::EAST, (string) self::SOUTH,
                $sourcePath,
            ], $sourceDirectory, 30);

            $layer = DroneImageryLayer::create(['name' => 'Review test', 'status' => 'queued', 'source_type' => 'single']);
            $storedPath = "drone-imagery/uploads/{$layer->id}/survey.tif";
            $localDisk->put($storedPath, file_get_contents($sourcePath));
            $layer->files()->create([
                'original_name' => 'survey.tif',
                'stored_path' => $storedPath,
                'mime_type' => 'image/tiff',
                'file_size' => $localDisk->size($storedPath),
                'sort_order' => 0,
            ]);

            (new ProcessDroneImagery($layer->id))->handle(app(GdalCommandRunner::class));
            $layer->refresh();
            $this->assertSame('awaiting_review', $layer->status, $layer->error_message ?? '');

            return $layer;
        } finally {
            File::deleteDirectory($sourceDirectory);
            File::deleteDirectory(storage_path('app/private/drone-imagery-test-inputs'));
        }
    }

    /** Segmentation columns as SegmentDroneImagery leaves them, with its result file written. */
    private function readyAiResult(DroneImageryLayer $layer): array
    {
        $path = "drone-imagery/processed/{$layer->id}/ai_extent.json";
        Storage::disk('local')->put($path, json_encode(['features' => [], 'stats' => []]));

        return [
            'segmentation_status' => 'ready',
            'segmentation_path' => $path,
            'segmentation_stats' => ['mangrove_area_ha' => 1.2],
            'segmented_at' => now(),
        ];
    }

    private function finalize(DroneImageryLayer $layer): void
    {
        (new FinalizeDroneImagery($layer->id))->handle(app(GdalCommandRunner::class));
        $layer->refresh();
    }

    private function edits(string $mode, array $polygons): array
    {
        return [...DroneImageryLayer::EMPTY_EDITS, 'mode' => $mode, 'polygons' => $polygons];
    }

    /** @return array<int, array{0: float, 1: float}> */
    private function westHalf(): array
    {
        return [[123.99, 10.01], [124.005, 10.01], [124.005, 9.98], [123.99, 9.98]];
    }

    private function alphaAt(DroneImageryLayer $layer, float $lng, float $lat): int
    {
        $raster = Storage::disk('local')->path($layer->final_mosaic_path ?? $layer->mosaic_path);
        $output = app(GdalCommandRunner::class)->run(
            ['gdallocationinfo', '-valonly', '-b', '4', '-wgs84', $raster, (string) $lng, (string) $lat],
            storage_path('app/private'),
            30
        );

        return (int) trim($output);
    }
}
