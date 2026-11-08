<?php

namespace Tests\Feature;

use App\Models\AIModel;
use App\Models\DroneImageryLayer;
use App\Models\User;
use App\Services\MangroveSegmentationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GenusSuggestionTest extends TestCase
{
    use RefreshDatabase;

    private string $workDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workDirectory = storage_path('framework/testing/genus-'.uniqid());
        File::ensureDirectoryExists($this->workDirectory);
        // Process::fake() replaces the interpreter, but the service still checks it exists.
        config(['ml.python' => 'python', 'ml.genus.model_path' => $this->workDirectory.'/missing.pt']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->workDirectory);

        parent::tearDown();
    }

    private function file(string $name): string
    {
        $path = $this->workDirectory.DIRECTORY_SEPARATOR.$name;
        file_put_contents($path, 'x');

        return $path;
    }

    public function test_genus_weights_prefer_the_active_classification_model_then_the_configured_file(): void
    {
        $service = app(MangroveSegmentationService::class);
        $this->assertNull($service->genusWeightsPath());

        $configured = $this->file('configured.pt');
        config(['ml.genus.model_path' => $configured]);
        $this->assertSame($configured, $service->genusWeightsPath());

        $registered = $this->file('registered.pt');
        AIModel::create([
            'name' => 'YOLO genus v1', 'model_type' => 'classification', 'version' => '1',
            'status' => 'completed', 'model_path' => $registered, 'is_active' => true,
        ]);
        // A segmentation checkpoint is never used for genus suggestions.
        AIModel::create([
            'name' => 'U-Net', 'model_type' => 'segmentation', 'version' => '1',
            'status' => 'completed', 'model_path' => $this->file('unet.pth'), 'is_active' => true,
        ]);
        $this->assertSame($registered, $service->genusWeightsPath());

        config(['ml.genus.enabled' => false]);
        $this->assertNull($service->genusWeightsPath());
    }

    public function test_predict_genera_runs_the_genus_script_with_the_unet_mask(): void
    {
        Process::fake(['*' => Process::result(
            "Ultralytics warning\n".json_encode([
                'ok' => true,
                'genera' => [['genus' => 'Rhizophora', 'pixels' => 80, 'share_percent' => 80.0, 'mean_confidence' => 0.4, 'detections' => 3]],
                'labeled_percent' => 25.0,
            ])
        )]);

        $raster = $this->file('seg_input.tif');
        $mask = $this->file('mask.png');
        $weights = $this->file('genus.pt');

        $result = app(MangroveSegmentationService::class)->predictGenera($raster, $mask, $weights);

        $this->assertSame('Rhizophora', $result['genera'][0]['genus']);
        Process::assertRan(function (PendingProcess $process) use ($raster, $mask, $weights) {
            $command = $process->command;

            return is_array($command)
                && str_ends_with($command[1], 'predict_genus.py')
                && in_array($raster, $command, true)
                && $command[array_search('--mask', $command, true) + 1] === $mask
                && $command[array_search('--weights', $command, true) + 1] === $weights
                && $command[array_search('--prob-conf', $command, true) + 1] === (string) config('ml.genus.probability_confidence')
                && in_array('--min-patch-px', $command, true);
        });
    }

    private function editableLayer(User $owner, array $genusSuggestion, bool $withGenusMap): DroneImageryLayer
    {
        $bounds = ['west' => 124.0, 'south' => 9.99, 'east' => 124.01, 'north' => 10.0];
        $layer = DroneImageryLayer::create([
            'user_id' => $owner->id,
            'name' => 'Survey A',
            'status' => 'awaiting_review',
            'source_type' => 'single',
            'bounds' => $bounds,
            'preview_bounds' => $bounds,
            'segmentation_status' => 'ready',
            'segmentation_stats' => ['surveyed_area_ha' => 10, 'edited' => false, 'genus_suggestion' => $genusSuggestion],
            'segmented_at' => now()->subHour(),
        ]);
        $layer->update([
            'mosaic_path' => "drone-imagery/processed/{$layer->id}/mosaic_3857.tif",
            'preview_path' => "drone-imagery/processed/{$layer->id}/preview.png",
        ]);
        Storage::disk('local')->put($layer->preview_path, 'png-bytes');
        if ($withGenusMap) {
            Storage::disk('local')->put($layer->genusLabelsPath(), 'png-bytes');
            Storage::disk('local')->put($layer->genusLabelsMetaPath(), json_encode(['classes' => ['Avicennia', 'Sonneratia']]));
        }

        return $layer;
    }

    private const SQUARE = [[124.001, 9.999], [124.00191, 9.999], [124.00191, 9.998], [124.001, 9.998]];

    private const ORIGINAL_SUGGESTION = [
        'genera' => [['genus' => 'Sonneratia', 'share_percent' => 100.0, 'mean_confidence' => 0.7, 'detections' => 0]],
        'labeled_percent' => 90.0,
        'probabilities' => [['genus' => 'Sonneratia', 'probability' => 1.0, 'mean_confidence' => 0.7, 'detections' => 0]],
        'probability_basis' => 'pixels',
        'recountable' => true,
    ];

    public function test_saving_edited_areas_recounts_the_genus_suggestion_from_the_genus_map(): void
    {
        Storage::fake('local');
        $sentAreas = null; // the temporary areas file is deleted after the run, so read it while it exists
        Process::fake(['*' => function (PendingProcess $process) use (&$sentAreas) {
            $command = $process->command;
            $sentAreas = json_decode((string) file_get_contents($command[array_search('--recount', $command, true) + 1]), true);

            return Process::result(json_encode([
                'ok' => true,
                'genera' => [['genus' => 'Avicennia', 'pixels' => 40, 'share_percent' => 100.0, 'mean_confidence' => 0.5]],
                'labeled_percent' => 40.0,
                'probabilities' => [
                    ['genus' => 'Avicennia', 'probability' => 0.8, 'mean_confidence' => 0.5],
                    ['genus' => 'Sonneratia', 'probability' => 0.2, 'mean_confidence' => 0.1],
                ],
                'probability_basis' => 'pixels',
            ]));
        }]);
        $owner = User::factory()->create(['role' => 'expert']);
        $layer = $this->editableLayer($owner, self::ORIGINAL_SUGGESTION, true);

        $this->actingAs($owner)
            ->putJson(route('drone-imagery.segmentation.update', $layer->id), [
                'areas' => [self::SQUARE],
                'offset' => ['east_m' => 2, 'north_m' => 0],
            ])
            ->assertOk()
            ->assertJsonPath('segmentation_stats.genus_suggestion.genera.0.genus', 'Avicennia')
            ->assertJsonPath('segmentation_stats.genus_suggestion.probabilities.1.genus', 'Sonneratia')
            ->assertJsonPath('segmentation_stats.genus_suggestion.recountable', true);

        $this->assertSame('Avicennia', $layer->fresh()->segmentation_stats['genus_suggestion']['genera'][0]['genus']);
        $this->assertNotNull($layer->fresh()->segmentation_stats['genus_suggestion']['recounted_at']);
        Process::assertRan(function (PendingProcess $process) use ($layer) {
            $command = $process->command;

            return is_array($command)
                && in_array('--recount', $command, true)
                && str_ends_with($command[1], 'predict_genus.py')
                && $command[array_search('--labels', $command, true) + 1] === Storage::disk('local')->path($layer->genusLabelsPath())
                && $command[array_search('--meta', $command, true) + 1] === Storage::disk('local')->path($layer->genusLabelsMetaPath());
        });
        // The saved rings and the offset they were drawn at, so the script can line them up with the map.
        $this->assertSame([self::SQUARE], $sentAreas['rings']);
        $this->assertEquals(['east_m' => 2, 'north_m' => 0], $sentAreas['offset']);
    }

    public function test_genus_preview_recounts_unsaved_areas_without_saving_them(): void
    {
        Storage::fake('local');
        Process::fake(['*' => Process::result(json_encode([
            'ok' => true,
            'genera' => [['genus' => 'Avicennia', 'pixels' => 40, 'share_percent' => 100.0, 'mean_confidence' => 0.5]],
            'labeled_percent' => 40.0,
            'probabilities' => [['genus' => 'Avicennia', 'probability' => 1.0, 'mean_confidence' => 0.5]],
            'probability_basis' => 'pixels',
        ]))]);
        $owner = User::factory()->create(['role' => 'expert']);
        $layer = $this->editableLayer($owner, self::ORIGINAL_SUGGESTION, true);
        $statsBefore = $layer->segmentation_stats;

        $this->actingAs($owner)
            ->postJson(route('drone-imagery.segmentation.genus-preview', $layer->id), [
                'areas' => [self::SQUARE],
                'offset' => ['east_m' => 0, 'north_m' => 0],
            ])
            ->assertOk()
            ->assertJsonPath('genus_suggestion.genera.0.genus', 'Avicennia')
            ->assertJsonPath('genus_suggestion.preview', true);

        // Nothing saved: the stored areas and suggestion are untouched.
        $this->assertEquals($statsBefore, $layer->fresh()->segmentation_stats);
        $this->assertNull($layer->fresh()->segmentation_path);
        Process::assertRan(fn (PendingProcess $process) => is_array($process->command) && in_array('--recount', $process->command, true));

        // Only the owner (or an expert reviewing it) may preview.
        $stranger = User::factory()->create(['role' => 'end_user']);
        $this->actingAs($stranger)
            ->postJson(route('drone-imagery.segmentation.genus-preview', $layer->id), [
                'areas' => [self::SQUARE],
                'offset' => ['east_m' => 0, 'north_m' => 0],
            ])
            ->assertStatus(404);
    }

    public function test_without_a_genus_map_the_old_suggestion_is_kept_and_marked_as_not_following_edits(): void
    {
        Storage::fake('local');
        Process::fake();
        $owner = User::factory()->create(['role' => 'expert']);
        $layer = $this->editableLayer($owner, [...self::ORIGINAL_SUGGESTION, 'recountable' => null], false);

        $this->actingAs($owner)
            ->putJson(route('drone-imagery.segmentation.update', $layer->id), [
                'areas' => [self::SQUARE],
                'offset' => ['east_m' => 0, 'north_m' => 0],
            ])
            ->assertOk()
            ->assertJsonPath('segmentation_stats.genus_suggestion.genera.0.genus', 'Sonneratia')
            ->assertJsonPath('segmentation_stats.genus_suggestion.recountable', false);

        Process::assertNothingRan();
    }

    public function test_a_failed_recount_keeps_the_previous_suggestion(): void
    {
        Storage::fake('local');
        Process::fake(['*' => Process::result(json_encode(['ok' => false, 'error' => 'broken map']), exitCode: 1)]);
        $owner = User::factory()->create(['role' => 'expert']);
        $layer = $this->editableLayer($owner, self::ORIGINAL_SUGGESTION, true);

        $this->actingAs($owner)
            ->putJson(route('drone-imagery.segmentation.update', $layer->id), [
                'areas' => [self::SQUARE],
                'offset' => ['east_m' => 0, 'north_m' => 0],
            ])
            ->assertOk()
            ->assertJsonPath('segmentation_stats.genus_suggestion.genera.0.genus', 'Sonneratia')
            ->assertJsonPath('segmentation_stats.genus_suggestion.recount_failed', true);
    }

    public function test_removing_every_area_clears_the_genus_suggestion(): void
    {
        Storage::fake('local');
        Process::fake();
        $owner = User::factory()->create(['role' => 'expert']);
        $layer = $this->editableLayer($owner, self::ORIGINAL_SUGGESTION, true);

        $this->actingAs($owner)
            ->putJson(route('drone-imagery.segmentation.update', $layer->id), [
                'areas' => [],
                'offset' => ['east_m' => 0, 'north_m' => 0],
            ])
            ->assertOk()
            ->assertJsonPath('segmentation_stats.genus_suggestion.genera', [])
            ->assertJsonPath('segmentation_stats.genus_suggestion.probabilities', []);

        Process::assertNothingRan();
    }

    public function test_admin_can_upload_a_genus_model_without_replacing_the_segmentation_model(): void
    {
        Storage::fake('local');
        Process::fake(['*' => Process::result(json_encode([
            'ok' => true, 'task' => 'segment', 'classes' => ['Avicennia', 'Rhizophora', 'Sonneratia'],
        ]))]);
        $admin = User::factory()->create(['role' => 'admin']);
        $unet = AIModel::create([
            'name' => 'U-Net', 'model_type' => 'segmentation', 'version' => '1',
            'status' => 'completed', 'model_path' => $this->file('unet.pth'), 'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.models.upload'), [
                'name' => 'YOLO genus v1',
                'version' => '1.0',
                'model_type' => 'classification',
                'model_file' => UploadedFile::fake()->create('best.pt', 100),
            ])
            ->assertRedirect(route('admin.models'))
            ->assertSessionHasNoErrors();

        $genusModel = AIModel::where('name', 'YOLO genus v1')->firstOrFail();
        $this->assertSame('classification', $genusModel->model_type);
        $this->assertTrue($genusModel->is_active);
        $this->assertStringEndsWith('.pt', $genusModel->model_path);
        $this->assertStringContainsString('Avicennia, Rhizophora, Sonneratia', $genusModel->description);
        $this->assertTrue($unet->fresh()->is_active);
        $this->assertSame($genusModel->model_path, app(MangroveSegmentationService::class)->genusWeightsPath());
    }

    public function test_genus_upload_rejects_wrong_extension_and_incompatible_models(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('admin.models.upload'), [
                'name' => 'Wrong file',
                'version' => '1.0',
                'model_type' => 'classification',
                'model_file' => UploadedFile::fake()->create('unet.pth', 100),
            ])
            ->assertSessionHasErrors('model_file');

        Process::fake(['*' => Process::result(
            json_encode(['ok' => false, 'error' => "Expected a YOLO segmentation model, got task 'detect'."]),
            exitCode: 1,
        )]);
        $this->actingAs($admin)
            ->post(route('admin.models.upload'), [
                'name' => 'Detector',
                'version' => '1.0',
                'model_type' => 'classification',
                'model_file' => UploadedFile::fake()->create('detector.pt', 100),
            ])
            ->assertSessionHasErrors('model_file');

        $this->assertSame(0, AIModel::count());
    }

    public function test_model_page_shows_both_models_in_use_and_activate_buttons(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        AIModel::create([
            'name' => 'U-Net', 'model_type' => 'segmentation', 'version' => '1',
            'status' => 'completed', 'model_path' => $this->file('unet.pth'), 'is_active' => true,
        ]);
        $older = AIModel::create([
            'name' => 'YOLO old', 'model_type' => 'classification', 'version' => '1',
            'status' => 'completed', 'model_path' => $this->file('old.pt'), 'is_active' => false,
        ]);
        AIModel::create([
            'name' => 'YOLO new', 'model_type' => 'classification', 'version' => '2',
            'status' => 'completed', 'model_path' => $this->file('new.pt'), 'is_active' => true,
        ]);
        AIModel::create([
            'name' => 'Missing file', 'model_type' => 'classification', 'version' => '1',
            'status' => 'completed', 'model_path' => $this->workDirectory.'/gone.pt', 'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.models'))
            ->assertOk()
            ->assertSee('Models In Use')
            ->assertSee(route('admin.models.activate', $older))
            ->assertDontSee(route('admin.models.activate', AIModel::where('name', 'Missing file')->first()));
    }

    public function test_admin_can_switch_back_to_an_earlier_model_of_the_same_type(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $unet = AIModel::create([
            'name' => 'U-Net', 'model_type' => 'segmentation', 'version' => '1',
            'status' => 'completed', 'model_path' => $this->file('unet.pth'), 'is_active' => true,
        ]);
        $older = AIModel::create([
            'name' => 'YOLO old', 'model_type' => 'classification', 'version' => '1',
            'status' => 'completed', 'model_path' => $this->file('old.pt'), 'is_active' => false,
        ]);
        $newer = AIModel::create([
            'name' => 'YOLO new', 'model_type' => 'classification', 'version' => '2',
            'status' => 'completed', 'model_path' => $this->file('new.pt'), 'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.models.activate', $older))
            ->assertRedirect(route('admin.models'))
            ->assertSessionHas('success');

        $this->assertTrue($older->fresh()->is_active);
        $this->assertFalse($newer->fresh()->is_active);
        $this->assertTrue($unet->fresh()->is_active, 'Activating a genus model leaves the segmentation model alone.');
        $this->assertSame($older->model_path, app(MangroveSegmentationService::class)->genusWeightsPath());
    }

    public function test_upload_saves_optional_accuracy_metric_and_dataset_size(): void
    {
        Storage::fake('local');
        Process::fake(['*' => Process::result(json_encode([
            'ok' => true, 'task' => 'segment', 'classes' => ['Avicennia', 'Rhizophora'],
        ]))]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('admin.models.upload'), [
                'name' => 'YOLO genus v3',
                'version' => '3.0',
                'model_type' => 'classification',
                'model_file' => UploadedFile::fake()->create('best.pt', 100),
                'accuracy' => '87.35',
                'accuracy_metric' => 'mAP50',
                'dataset_size' => '1200',
            ])
            ->assertSessionHasNoErrors();

        $model = AIModel::where('name', 'YOLO genus v3')->firstOrFail();
        $this->assertEqualsWithDelta(0.8735, $model->accuracy, 0.00001);
        $this->assertSame('mAP50', $model->accuracy_metric);
        $this->assertSame(1200, $model->dataset_size);
    }

    public function test_accuracy_needs_a_metric_and_must_be_a_percentage(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('admin.models.upload'), [
                'name' => 'No metric',
                'version' => '1.0',
                'model_type' => 'classification',
                'model_file' => UploadedFile::fake()->create('best.pt', 100),
                'accuracy' => '150',
                'dataset_size' => '0',
            ])
            ->assertSessionHasErrors(['accuracy', 'accuracy_metric', 'dataset_size']);

        $this->assertSame(0, AIModel::count());
    }

    public function test_admin_adds_and_removes_accuracy_for_an_existing_model_and_it_appears_in_the_chart(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $unet = AIModel::create([
            'name' => 'U-Net', 'model_type' => 'segmentation', 'version' => '1',
            'status' => 'completed', 'model_path' => $this->file('unet.pth'), 'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.models'))
            ->assertSee('Add accuracy')
            ->assertSee('No accuracy entered yet');

        $this->actingAs($admin)
            ->put(route('admin.models.metrics', $unet), [
                'accuracy' => '91.2', 'accuracy_metric' => 'IoU', 'dataset_size' => '850',
            ])
            ->assertRedirect(route('admin.models'))
            ->assertSessionHas('success');

        $unet->refresh();
        $this->assertSame(91.2, $unet->accuracyPercent());
        $this->assertSame('IoU', $unet->accuracy_metric);
        $this->assertSame(850, $unet->dataset_size);

        $this->actingAs($admin)
            ->get(route('admin.models'))
            ->assertSee('id="performanceChart"', false)
            ->assertSee('91.2%')
            ->assertSee('Edit accuracy');

        // Clearing the accuracy also clears its metric.
        $this->actingAs($admin)->put(route('admin.models.metrics', $unet), ['accuracy' => '', 'accuracy_metric' => 'IoU']);
        $this->assertNull($unet->fresh()->accuracy);
        $this->assertNull($unet->fresh()->accuracy_metric);
    }

    public function test_invalid_accuracy_edit_reopens_that_models_dialog_with_the_errors(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $unet = AIModel::create([
            'name' => 'U-Net', 'model_type' => 'segmentation', 'version' => '1',
            'status' => 'completed', 'model_path' => $this->file('unet.pth'), 'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.models.metrics', $unet), ['accuracy' => '120'])
            ->assertRedirect(route('admin.models'))
            ->assertSessionHasErrorsIn('metrics', ['accuracy', 'accuracy_metric']);

        $html = $this->actingAs($admin)
            ->followingRedirects()
            ->put(route('admin.models.metrics', $unet), ['accuracy' => '120'])
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Accuracy must be between 0 and 100', $html);
        // The page reopens this model's dialog with what was typed.
        $this->assertStringContainsString('[data-edit-metrics][data-model-id="' . $unet->id . '"]', $html);
        $this->assertStringContainsString('"accuracy":"120"', $html);

        $this->assertNull($unet->fresh()->accuracy);
    }

    private function yoloValidationResult(): string
    {
        return json_encode([
            'ok' => true, 'task' => 'segment', 'classes' => ['Avicennia', 'Nypa', 'Rhizophora', 'Sonneratia'],
            'training' => [
                'map50_mask' => 0.38002, 'map50_95_mask' => 0.17759, 'precision_mask' => 0.75975, 'recall_mask' => 0.30629,
                'dataset' => 'mangrove-canopy-2', 'base_model' => 'yolo11s-seg.pt', 'epochs' => 100, 'imgsz' => 640,
                'trained_at' => '2026-11-08T01:48:19+00:00',
            ],
        ]);
    }

    public function test_yolo_upload_fills_accuracy_from_the_results_saved_in_the_checkpoint(): void
    {
        Storage::fake('local');
        Process::fake(['*' => Process::result($this->yoloValidationResult())]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('admin.models.upload'), [
                'name' => 'Roboflow genus', 'version' => '2', 'model_type' => 'classification',
                'model_file' => UploadedFile::fake()->create('best.pt', 100),
            ])
            ->assertSessionHasNoErrors();

        $model = AIModel::where('name', 'Roboflow genus')->firstOrFail();
        $this->assertSame(38.0, $model->accuracyPercent());
        $this->assertSame('mAP50 (mask)', $model->accuracy_metric);
        $this->assertStringContainsString('Roboflow dataset mangrove-canopy-2', $model->description);
        $this->assertStringContainsString('precision 76%', $model->description);
        $this->assertSame('2026-11-08', $model->training_date?->toDateString());
    }

    public function test_figures_typed_at_upload_win_over_the_checkpoint(): void
    {
        Storage::fake('local');
        Process::fake(['*' => Process::result($this->yoloValidationResult())]);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.models.upload'), [
            'name' => 'Roboflow genus', 'version' => '2', 'model_type' => 'classification',
            'model_file' => UploadedFile::fake()->create('best.pt', 100),
            'accuracy' => '41.5', 'accuracy_metric' => 'mAP50 (test set)',
        ])->assertSessionHasNoErrors();

        $model = AIModel::where('name', 'Roboflow genus')->firstOrFail();
        $this->assertSame(41.5, $model->accuracyPercent());
        $this->assertSame('mAP50 (test set)', $model->accuracy_metric);
    }

    public function test_read_from_file_fills_an_existing_yolo_model_and_rejects_a_unet(): void
    {
        Process::fake(['*' => Process::result($this->yoloValidationResult())]);
        $admin = User::factory()->create(['role' => 'admin']);
        $yolo = AIModel::create([
            'name' => 'YOLO Genus v2', 'model_type' => 'classification', 'version' => '2',
            'status' => 'completed', 'model_path' => $this->file('best-2.pt'), 'is_active' => true,
        ]);
        $unet = AIModel::create([
            'name' => 'U-Net', 'model_type' => 'segmentation', 'version' => '1',
            'status' => 'completed', 'model_path' => $this->file('unet.pth'), 'is_active' => true,
        ]);

        $this->actingAs($admin)->get(route('admin.models'))
            ->assertSee(route('admin.models.metrics.checkpoint', $yolo))
            ->assertDontSee(route('admin.models.metrics.checkpoint', $unet));

        $this->actingAs($admin)
            ->post(route('admin.models.metrics.checkpoint', $yolo))
            ->assertRedirect(route('admin.models'))
            ->assertSessionHas('success', 'YOLO Genus v2: 38% mAP50 (mask) on mangrove-canopy-2, read from the model file.');

        $this->assertSame(38.0, $yolo->fresh()->accuracyPercent());

        $this->actingAs($admin)
            ->post(route('admin.models.metrics.checkpoint', $unet))
            ->assertSessionHas('error');
        $this->assertNull($unet->fresh()->accuracy);
    }

    public function test_read_from_file_explains_when_the_checkpoint_has_no_results(): void
    {
        Process::fake(['*' => Process::result(json_encode(['ok' => true, 'task' => 'segment', 'classes' => ['Avicennia'], 'training' => null]))]);
        $admin = User::factory()->create(['role' => 'admin']);
        $yolo = AIModel::create([
            'name' => 'Exported', 'model_type' => 'classification', 'version' => '1',
            'status' => 'completed', 'model_path' => $this->file('exported.pt'), 'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.models.metrics.checkpoint', $yolo))
            ->assertSessionHas('error', 'Exported has no training results saved in its file. Enter the figures from Roboflow or your training run instead.');
    }

    public function test_a_model_whose_file_is_missing_cannot_be_activated(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $gone = AIModel::create([
            'name' => 'Gone', 'model_type' => 'segmentation', 'version' => '1',
            'status' => 'completed', 'model_path' => $this->workDirectory.'/gone.pth', 'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.models.activate', $gone))
            ->assertSessionHas('error');

        $this->assertFalse($gone->fresh()->is_active);

        $resident = User::factory()->create(['role' => 'end_user']);
        $this->actingAs($resident)
            ->post(route('admin.models.activate', $gone))
            ->assertRedirect($resident->homeRoute());
    }
}
