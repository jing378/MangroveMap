<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\MangroveSegmentationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Tests\TestCase;

/**
 * The hosted Roboflow canopy Workflow as the genus model (ML_GENUS_MODEL=roboflow). Offline: the Python
 * script is faked. The live check against the real workflow is ml/smoke_roboflow_genus.py.
 */
class RoboflowGenusTest extends TestCase
{
    use RefreshDatabase;

    private string $workDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workDirectory = storage_path('framework/testing/roboflow-genus-'.uniqid());
        File::ensureDirectoryExists($this->workDirectory);
        config([
            'ml.python' => 'python',
            'ml.genus.enabled' => true,
            'ml.genus.model' => 'roboflow',
            'ml.genus.model_path' => $this->workDirectory.'/missing.pt',
            'ml.drone.extent_model' => 'unet',
            'ml.roboflow.api_key' => 'test-secret-key',
        ]);
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

    private function genusResult(string $device): string
    {
        return json_encode([
            'ok' => true, 'classes' => ['Avicennia', 'Nypa', 'Rhizophora', 'Sonneratia'],
            'genera' => [['genus' => 'Sonneratia', 'pixels' => 80, 'share_percent' => 80.0, 'coverage_percent' => 40.0, 'mean_confidence' => 0.75]],
            'probabilities' => [['genus' => 'Sonneratia', 'probability' => 0.8, 'pixels' => 80, 'mean_confidence' => 0.75]],
            'pixels_target' => 200, 'pixels_labeled' => 100, 'labeled_percent' => 50.0, 'tiles' => 9, 'device' => $device,
        ]);
    }

    public function test_the_genus_runs_on_roboflow_only_when_configured_with_an_api_key(): void
    {
        $service = app(MangroveSegmentationService::class);
        $this->assertSame('roboflow', $service->genusModel());
        $this->assertTrue($service->genusAvailable(), 'The workflow needs no weights file.');

        config(['ml.roboflow.api_key' => '']);
        $this->assertSame('yolo', $service->genusModel());
        $this->assertFalse($service->genusAvailable(), 'Without a key or weights there is no genus model.');

        config(['ml.roboflow.api_key' => 'k', 'ml.genus.enabled' => false]);
        $this->assertFalse($service->genusAvailable());

        config(['ml.genus.enabled' => true, 'ml.genus.model' => 'yolo']);
        $this->assertSame('yolo', $service->genusModel());
    }

    public function test_classify_genera_runs_the_canopy_workflow_with_the_key_in_the_environment_only(): void
    {
        config(['ml.roboflow.parallel' => 3, 'ml.genus.input_size' => 640, 'ml.genus.tile_overlap' => 160]);
        Process::fake(['*' => Process::result($this->genusResult('roboflow'))]);

        $raster = $this->file('genus_input.tif');
        $areas = $this->file('areas.json');
        $result = app(MangroveSegmentationService::class)->classifyGenera($raster, $this->workDirectory.'/labels.png', 5, [
            'areas' => $areas,
            'canopies_out' => $this->workDirectory.'/canopies.json',
            'bounds' => [0, 10, 10, 0],
        ]);

        $this->assertSame('roboflow', $result['genus_model']);
        $this->assertSame(config('ml.roboflow.genus_workflow'), $result['genus_workflow']);
        $this->assertSame('Sonneratia', $result['genera'][0]['genus']);
        Process::assertRanTimes(fn (PendingProcess $process) => str_ends_with($process->command[1], 'predict_genus.py'), 1);
        Process::assertRan(function (PendingProcess $process) use ($raster, $areas) {
            $command = $process->command;
            $after = fn (string $flag) => $command[array_search($flag, $command, true) + 1] ?? null;

            return in_array('--roboflow', $command, true)
                && ! in_array('--weights', $command, true)
                && $after('--raster') === $raster
                && $after('--areas') === $areas
                && $after('--rf-workspace') === 'denje-s-works'
                && $after('--rf-workflow') === 'mangrove-canopy-3-rfdetr-seg-small-t1-vmangrove-canopy-3-rfdetr-seg-small-t1-logic'
                && $after('--rf-classes') === 'Avicennia,Nypa,Rhizophora,Sonneratia'
                && $after('--rf-parallel') === '3'
                && $after('--size') === '640'
                && $after('--overlap') === '160'
                && $after('--min-patch-px') === '5'
                && ! str_contains(implode(' ', $command), 'test-secret-key')
                && ($process->environment['ROBOFLOW_API_KEY'] ?? null) === 'test-secret-key';
        });
    }

    public function test_classify_genera_falls_back_to_the_yolo_weights_when_roboflow_fails(): void
    {
        $weights = $this->file('best.pt');
        config(['ml.genus.model_path' => $weights]);
        Process::fake(fn (PendingProcess $process) => in_array('--roboflow', $process->command, true)
            ? Process::result(json_encode(['ok' => false, 'error' => 'Roboflow workflow request failed after 4 attempts']), exitCode: 1)
            : Process::result($this->genusResult('cpu')));

        $result = app(MangroveSegmentationService::class)->classifyGenera($this->file('genus_input.tif'), null, 0, ['areas' => $this->file('areas.json')]);

        $this->assertSame('yolo', $result['genus_model']);
        Process::assertRan(function (PendingProcess $process) use ($weights) {
            $command = $process->command;

            return ($command[array_search('--weights', $command, true) + 1] ?? null) === $weights
                && ! in_array('--roboflow', $command, true);
        });
    }

    public function test_a_roboflow_failure_without_yolo_weights_is_reported(): void
    {
        Process::fake(['*' => Process::result(json_encode(['ok' => false, 'error' => 'Roboflow refused the API key (HTTP 401).']), exitCode: 1)]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HTTP 401');

        app(MangroveSegmentationService::class)->classifyGenera($this->file('genus_input.tif'), null, 0, ['areas' => $this->file('areas.json')]);
    }

    public function test_the_admin_sees_the_canopy_workflow_on_the_ai_models_page_and_dashboard(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('admin.models'))
            ->assertOk()
            ->assertSee('Canopies and genus are found by')
            ->assertSee('mangrove-canopy-3-rfdetr-seg-small-t1')
            ->assertSee('No YOLO genus model is available as a fallback');

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Roboflow workflow');

        // Not switched on: still listed, as not in use, with how to switch it on.
        config(['ml.genus.model' => 'yolo']);
        $this->actingAs($admin)->get(route('admin.models'))
            ->assertOk()
            ->assertDontSee('Canopies and genus are found by')
            ->assertSee('Can find the canopies and genus')
            ->assertSee('mangrove-canopy-3-rfdetr-seg-small-t1')
            ->assertSee('Not in use')
            ->assertSee('ML_GENUS_MODEL=roboflow');

        // Without an API key no Roboflow workflow is listed.
        config(['ml.roboflow.api_key' => '']);
        $this->actingAs($admin)->get(route('admin.models'))
            ->assertOk()
            ->assertDontSee('Roboflow workflow');
    }
}
