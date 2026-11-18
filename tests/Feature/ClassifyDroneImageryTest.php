<?php

namespace Tests\Feature;

use App\Jobs\ClassifyDroneImagery;
use App\Jobs\SegmentDroneImagery;
use App\Models\DroneImageryLayer;
use App\Models\User;
use App\Services\GdalCommandRunner;
use App\Services\MangroveSegmentationService;
use App\Services\SegmentationFeatureBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** The genus classification is its own job, separate from the AI delineation (SegmentDroneImagery). */
class ClassifyDroneImageryTest extends TestCase
{
    use RefreshDatabase;

    private string $workDirectory;

    private const RESULT = [
        'ok' => true,
        'classes' => ['Avicennia', 'Nypa', 'Rhizophora', 'Sonneratia'],
        'genera' => [['genus' => 'Rhizophora', 'pixels' => 80, 'share_percent' => 80.0, 'mean_confidence' => 0.55]],
        'labeled_percent' => 60.0,
        'probabilities' => [['genus' => 'Rhizophora', 'probability' => 0.9, 'mean_confidence' => 0.5]],
        'probability_basis' => 'pixels',
        'device' => 'cpu',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->workDirectory = storage_path('framework/testing/classify-'.uniqid());
        File::ensureDirectoryExists($this->workDirectory);
        $weights = $this->workDirectory.DIRECTORY_SEPARATOR.'best.pt';
        file_put_contents($weights, 'x');
        // Process::fake() replaces the interpreter, but the service still checks it exists.
        config(['ml.python' => 'python', 'ml.genus.model_path' => $weights, 'ml.drone.extent_model' => 'unet']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->workDirectory);

        parent::tearDown();
    }

    /** GDAL stand-in: gdalinfo describes a small EPSG:3857 raster, gdal_translate writes its output file. */
    private function fakeGdal(): GdalCommandRunner
    {
        return new class extends GdalCommandRunner
        {
            public array $commands = [];

            public function run(array $arguments, string $workingDirectory, int $timeout): string
            {
                $this->commands[] = $arguments;
                if ($arguments[0] === 'gdalinfo') {
                    return json_encode([
                        'geoTransform' => [13804000.0, 0.15, 0, 1118000.0, 0, -0.15],
                        'size' => [200, 100],
                        'bands' => [[], [], [], ['colorInterpretation' => 'Alpha']],
                    ]);
                }
                if ($arguments[0] === 'gdal_translate') {
                    file_put_contents(end($arguments), 'tif');
                }

                return '';
            }
        };
    }

    private function segmentedLayer(User $owner, array $attributes = []): DroneImageryLayer
    {
        $bounds = ['west' => 124.0, 'south' => 9.99, 'east' => 124.01, 'north' => 10.0];
        $layer = DroneImageryLayer::create(array_merge([
            'user_id' => $owner->id,
            'name' => 'Survey A',
            'status' => 'awaiting_review',
            'source_type' => 'single',
            'bounds' => $bounds,
            'preview_bounds' => $bounds,
            'segmentation_status' => 'ready',
            'segmentation_stats' => ['mangrove_area_ha' => 1.5, 'edited' => false, 'offset' => ['east_m' => 0, 'north_m' => 0]],
            'segmented_at' => now()->subMinutes(5),
        ], $attributes));
        $layer->update([
            'mosaic_path' => "drone-imagery/processed/{$layer->id}/mosaic_3857.tif",
            'preview_path' => "drone-imagery/processed/{$layer->id}/preview.png",
            'segmentation_path' => "drone-imagery/processed/{$layer->id}/ai_extent.json",
        ]);
        Storage::disk('local')->put($layer->mosaic_path, 'tif');
        Storage::disk('local')->put($layer->preview_path, 'png');
        Storage::disk('local')->put($layer->segmentation_path, json_encode([
            'features' => [['type' => 'area', 'coords' => [[9.999, 124.001], [9.999, 124.002], [9.998, 124.002], [9.998, 124.001]]]],
            'stats' => $layer->segmentation_stats,
        ]));

        return $layer->fresh();
    }

    private function runJob(DroneImageryLayer $layer, ?GdalCommandRunner $gdal = null): void
    {
        (new ClassifyDroneImagery($layer->id))->handle($gdal ?? $this->fakeGdal(), app(MangroveSegmentationService::class));
    }

    /** predict_genus.py stand-in: writes the genus map and canopies where asked, returns $result. */
    private function fakeGenusModel(array $result = self::RESULT, ?callable $during = null, ?array &$sentAreas = null): void
    {
        Process::fake(['*' => function (PendingProcess $process) use ($result, $during, &$sentAreas) {
            $command = $process->command;
            $after = fn (string $flag) => $command[array_search($flag, $command, true) + 1] ?? null;
            if (in_array('--recount', $command, true)) {
                return Process::result(json_encode([
                    'ok' => true,
                    'genera' => [['genus' => 'Nypa', 'pixels' => 10, 'share_percent' => 100.0, 'mean_confidence' => 0.4]],
                    'labeled_percent' => 50.0,
                    'probabilities' => [['genus' => 'Nypa', 'probability' => 1.0, 'mean_confidence' => 0.4]],
                    'probability_basis' => 'pixels',
                ]));
            }
            if (in_array('--areas', $command, true)) {
                $sentAreas = json_decode((string) file_get_contents($after('--areas')), true);
            }
            if ($after('--labels-out')) {
                file_put_contents($after('--labels-out'), 'png');
            }
            if ($after('--canopies-out')) {
                file_put_contents($after('--canopies-out'), json_encode([
                    'count' => 2, 'kept' => 2, 'by_genus' => ['Rhizophora' => 2],
                    'canopies' => [['genus' => 'Rhizophora', 'center' => [9.9985, 124.0015]], ['genus' => 'Rhizophora', 'center' => [9.9986, 124.0016]]],
                ]));
            }
            if ($during) {
                $during();
            }

            return Process::result(json_encode($result));
        }]);
    }

    public function test_it_classifies_the_genus_inside_the_current_areas_and_keeps_its_own_results(): void
    {
        Storage::fake('local');
        $sentAreas = null;
        $this->fakeGenusModel(sentAreas: $sentAreas);
        $owner = User::factory()->create(['role' => 'end_user']);
        $layer = $this->segmentedLayer($owner, ['classification_status' => 'queued']);
        $areasBefore = $layer->segmentation_stats;

        $this->runJob($layer);

        $layer->refresh();
        $this->assertSame('ready', $layer->classification_status);
        $this->assertNotNull($layer->classified_at);
        $this->assertSame('Rhizophora', $layer->classification_stats['genus_suggestion']['genera'][0]['genus']);
        $this->assertTrue($layer->classification_stats['genus_suggestion']['recountable']);
        $this->assertSame(2, $layer->classification_stats['canopy_count']);
        $this->assertSame(['Rhizophora' => 2], $layer->classification_stats['canopies_by_genus']);
        // The delineation is not touched.
        $this->assertEquals($areasBefore, $layer->segmentation_stats);
        $this->assertSame('ready', $layer->segmentation_status);
        // Payloads show both together, as before the split.
        $this->assertSame(1.5, $layer->aiStats()['mangrove_area_ha']);
        $this->assertSame(2, $layer->aiStats()['canopy_count']);

        Storage::disk('local')->assertExists([$layer->genusLabelsPath(), $layer->genusLabelsMetaPath(), $layer->canopiesPath()]);
        $meta = json_decode(Storage::disk('local')->get($layer->genusLabelsMetaPath()), true);
        $this->assertEquals([13804000.0, 1118000.0, 13804030.0, 1117985.0], $meta['bounds']);
        $this->assertSame('Nypa', $meta['classes'][1]);

        // Counted inside the saved areas ([lng, lat] rings at their offset), not a U-Net mask.
        Process::assertRan(fn (PendingProcess $process) => in_array('--areas', $process->command, true)
            && ! in_array('--mask', $process->command, true)
            && in_array('--canopies-out', $process->command, true));
        $this->assertSame([[124.001, 9.999], [124.002, 9.999], [124.002, 9.998], [124.001, 9.998]], $sentAreas['rings'][0]);
        $this->assertEquals(['east_m' => 0, 'north_m' => 0], $sentAreas['offset']);
        $this->assertEquals([13804000.0, 1118000.0, 13804030.0, 1117985.0], $sentAreas['raster']['bounds']);
    }

    public function test_areas_saved_while_it_runs_are_recounted_at_the_end(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create(['role' => 'end_user']);
        $layer = $this->segmentedLayer($owner, ['classification_status' => 'queued']);
        // The owner saves corrected areas while the model runs.
        $this->fakeGenusModel(during: fn () => DroneImageryLayer::whereKey($layer->id)->update([
            'segmentation_stats' => json_encode(['mangrove_area_ha' => 0.2, 'edited' => true, 'offset' => ['east_m' => 1, 'north_m' => 0]]),
            'segmented_at' => now()->addMinute(),
        ]));

        $this->runJob($layer);

        $suggestion = $layer->fresh()->classification_stats['genus_suggestion'];
        $this->assertSame('Nypa', $suggestion['genera'][0]['genus']);
        $this->assertNotNull($suggestion['recounted_at']);
        Process::assertRan(fn (PendingProcess $process) => in_array('--recount', $process->command, true));
    }

    public function test_a_failed_classification_leaves_the_areas_alone(): void
    {
        Storage::fake('local');
        Process::fake(['*' => Process::result(json_encode(['ok' => false, 'error' => 'CUDA out of memory']), exitCode: 1)]);
        $owner = User::factory()->create(['role' => 'end_user']);
        $layer = $this->segmentedLayer($owner, ['classification_status' => 'queued']);

        $this->runJob($layer);

        $layer->refresh();
        $this->assertSame('failed', $layer->classification_status);
        $this->assertStringContainsString('CUDA out of memory', $layer->classification_error);
        $this->assertArrayHasKey('error', $layer->classification_stats['genus_suggestion']);
        $this->assertSame('ready', $layer->segmentation_status);
        $this->assertSame(1.5, $layer->segmentation_stats['mangrove_area_ha']);
    }

    public function test_it_needs_the_mangrove_areas_first(): void
    {
        Storage::fake('local');
        Process::fake();
        $owner = User::factory()->create(['role' => 'end_user']);
        $layer = $this->segmentedLayer($owner, ['classification_status' => 'queued']);
        $layer->update(['segmentation_status' => 'failed']);

        $this->runJob($layer);

        $this->assertSame('failed', $layer->fresh()->classification_status);
        $this->assertStringContainsString('mangrove areas', $layer->fresh()->classification_error);
        Process::assertNothingRan();
    }

    public function test_queue_for_does_nothing_without_a_genus_model(): void
    {
        Storage::fake('local');
        Queue::fake();
        $owner = User::factory()->create(['role' => 'end_user']);
        $layer = $this->segmentedLayer($owner);

        $this->assertTrue(ClassifyDroneImagery::queueFor($layer));
        Queue::assertPushedOn('drone-imagery', ClassifyDroneImagery::class, fn ($job) => $job->layerId === $layer->id);
        $this->assertSame('queued', $layer->fresh()->classification_status);

        config(['ml.genus.model_path' => $this->workDirectory.'/missing.pt']);
        $other = $this->segmentedLayer($owner);
        $this->assertFalse(ClassifyDroneImagery::queueFor($other));
        $this->assertNull($other->fresh()->classification_status);
        Queue::assertPushed(ClassifyDroneImagery::class, 1);
    }

    public function test_a_new_delineation_run_clears_the_old_genus_results(): void
    {
        Storage::fake('local');
        Queue::fake();
        $owner = User::factory()->create(['role' => 'end_user']);
        $layer = $this->segmentedLayer($owner, [
            'classification_status' => 'ready',
            'classification_stats' => ['genus_suggestion' => ['genera' => []], 'canopy_count' => 3],
            'classified_at' => now(),
        ]);
        Storage::disk('local')->put($layer->canopiesPath(), '{}');
        Storage::disk('local')->delete($layer->mosaic_path); // the delineation fails early

        (new SegmentDroneImagery($layer->id))->handle(
            $this->fakeGdal(), app(MangroveSegmentationService::class), app(SegmentationFeatureBuilder::class)
        );

        $layer->refresh();
        $this->assertSame('failed', $layer->segmentation_status);
        $this->assertNull($layer->classification_status);
        $this->assertNull($layer->classification_stats);
        Storage::disk('local')->assertMissing($layer->canopiesPath());
        // A failed delineation does not queue the genus classification.
        Queue::assertNotPushed(ClassifyDroneImagery::class);
    }

    public function test_the_owner_can_rerun_only_the_genus_classification(): void
    {
        Storage::fake('local');
        Queue::fake();
        $owner = User::factory()->create(['role' => 'end_user']);
        $layer = $this->segmentedLayer($owner, [
            'classification_status' => 'ready',
            'classification_stats' => ['genus_suggestion' => ['genera' => [['genus' => 'Sonneratia', 'share_percent' => 100]]]],
        ]);
        $url = route('drone-imagery.classification.store', $layer->id);

        // Not someone else's layer.
        $this->actingAs(User::factory()->create(['role' => 'end_user']))->postJson($url)->assertNotFound();

        $this->actingAs($owner)
            ->postJson($url)
            ->assertAccepted()
            ->assertJsonPath('classification_status', 'queued');
        Queue::assertPushedOn('drone-imagery', ClassifyDroneImagery::class, fn ($job) => $job->layerId === $layer->id);
        Queue::assertNotPushed(SegmentDroneImagery::class);
        $layer->refresh();
        $this->assertSame('queued', $layer->classification_status);
        $this->assertSame('ready', $layer->segmentation_status); // the areas stay

        // The status payload shows it, separate from the delineation.
        $this->actingAs($owner)
            ->getJson(route('drone-imagery.show', $layer->id))
            ->assertOk()
            ->assertJsonPath('segmentation_status', 'ready')
            ->assertJsonPath('classification_status', 'queued')
            ->assertJsonPath('segmentation_stats.mangrove_area_ha', 1.5);

        // Not twice at once, and not while the areas are being detected.
        $this->actingAs($owner)->postJson($url)->assertStatus(409);
        $layer->update(['classification_status' => 'ready', 'segmentation_status' => 'processing']);
        $this->actingAs($owner)->postJson($url)->assertStatus(409);

        // Not without a genus model.
        $layer->update(['segmentation_status' => 'ready']);
        config(['ml.genus.model_path' => $this->workDirectory.'/missing.pt']);
        $this->actingAs($owner)
            ->postJson($url)
            ->assertStatus(409)
            ->assertJsonPath('message', 'No genus model is installed. An admin can upload one on the AI Models page.');
        Queue::assertPushed(ClassifyDroneImagery::class, 1);
    }

    public function test_areas_can_be_edited_while_the_genus_is_detected(): void
    {
        Storage::fake('local');
        Process::fake();
        $owner = User::factory()->create(['role' => 'end_user']);
        $layer = $this->segmentedLayer($owner, ['classification_status' => 'processing']);

        $this->actingAs($owner)
            ->putJson(route('drone-imagery.segmentation.update', $layer->id), [
                'areas' => [[[124.001, 9.999], [124.0019, 9.999], [124.0019, 9.998], [124.001, 9.998]]],
                'offset' => ['east_m' => 0, 'north_m' => 0],
            ])
            ->assertOk()
            ->assertJsonPath('segmentation_stats.edited', true)
            ->assertJsonPath('classification_status', 'processing');

        // Nothing to recount yet: the job counts inside the saved areas when it finishes.
        Process::assertNothingRan();
        // The live preview waits for it.
        $this->actingAs($owner)
            ->postJson(route('drone-imagery.segmentation.genus-preview', $layer->id), [
                'areas' => [],
                'offset' => ['east_m' => 0, 'north_m' => 0],
            ])
            ->assertStatus(409);
    }
}
