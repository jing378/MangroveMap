<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\MangroveSegmentationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * The hosted Roboflow Workflow as the drone extent model (ML_DRONE_EXTENT_MODEL=roboflow). Offline: the
 * Python script is faked. The live check against the real workflow is ml/smoke_roboflow.py.
 */
class RoboflowExtentTest extends TestCase
{
    use RefreshDatabase;

    private string $workDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workDirectory = storage_path('framework/testing/roboflow-'.uniqid());
        File::ensureDirectoryExists($this->workDirectory);
        config([
            'ml.python' => 'python',
            'ml.genus.model_path' => $this->workDirectory.'/missing.pt',
            'ml.model_path' => $this->file('unet.pth'),
            'ml.drone.extent_model' => 'roboflow',
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

    public function test_roboflow_delineates_only_when_configured_with_an_api_key(): void
    {
        $service = app(MangroveSegmentationService::class);
        $this->assertSame('roboflow', $service->extentModel());

        config(['ml.roboflow.api_key' => '']);
        $this->assertSame('unet', $service->extentModel(), 'Without an API key the U-Net delineates.');

        config(['ml.roboflow.api_key' => 'k', 'ml.drone.extent_model' => 'unet']);
        $this->assertSame('unet', $service->extentModel());
    }

    public function test_predict_extent_runs_the_workflow_with_the_key_in_the_environment_only(): void
    {
        config(['ml.roboflow.tile_size' => 768, 'ml.roboflow.parallel' => 2, 'ml.roboflow.classes' => 'mangrove-area']);
        Process::fake(['*' => Process::result(json_encode([
            'ok' => true, 'tiles' => 9, 'pixels_mangrove' => 500, 'pixels_valid' => 1000,
            'mangrove_coverage_percent' => 50.0, 'mean_confidence' => 0.95, 'device' => 'roboflow',
        ]))]);

        $raster = $this->file('seg_input.tif');
        $mask = $this->workDirectory.DIRECTORY_SEPARATOR.'mask.png';
        $result = app(MangroveSegmentationService::class)->predictExtent($raster, $mask, ['close_iterations' => 3]);

        $this->assertSame('roboflow', $result['extent_model']);
        $this->assertSame(500, $result['pixels_mangrove']);
        $this->assertSame(9, $result['tiles']);
        Process::assertRanTimes(fn (PendingProcess $process) => str_ends_with($process->command[1], 'predict_roboflow.py'), 1);
        Process::assertRan(function (PendingProcess $process) use ($raster, $mask) {
            $command = $process->command;
            $after = fn (string $flag) => $command[array_search($flag, $command, true) + 1] ?? null;

            return $after('--raster') === $raster
                && $after('--mask') === $mask
                && $after('--workspace') === 'denje-s-works'
                && $after('--workflow') === config('ml.roboflow.workflow')
                && $after('--tile-size') === '768'
                && $after('--parallel') === '2'
                && $after('--close') === '3'
                && ! str_contains(implode(' ', $command), 'test-secret-key')
                && ($process->environment['ROBOFLOW_API_KEY'] ?? null) === 'test-secret-key';
        });
    }

    public function test_the_admin_sees_the_roboflow_workflow_on_the_ai_models_page_and_dashboard(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('admin.models'))
            ->assertOk()
            ->assertSee('Mangrove areas are drawn by')
            ->assertSee('Roboflow workflow')
            ->assertSee('unet.pth is used instead', false);

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Roboflow workflow');

        config(['ml.roboflow.api_key' => '']);
        $this->actingAs($admin)->get(route('admin.models'))
            ->assertOk()
            ->assertDontSee('Mangrove areas are drawn by');
    }

    public function test_predict_extent_falls_back_to_the_unet_when_roboflow_fails(): void
    {
        Process::fake([
            '*predict_roboflow.py*' => Process::result(json_encode(['ok' => false, 'error' => 'Roboflow workflow request failed after 4 attempts']), exitCode: 1),
            '*predict.py*' => Process::result(json_encode(['ok' => true, 'pixels_mangrove' => 7, 'pixels_valid' => 10, 'mangrove_coverage_percent' => 70.0])),
        ]);

        $result = app(MangroveSegmentationService::class)->predictExtent($this->file('seg_input.tif'), $this->workDirectory.'/mask.png');

        $this->assertSame('unet', $result['extent_model']);
        $this->assertSame(7, $result['pixels_mangrove']);
        Process::assertRan(fn (PendingProcess $process) => str_ends_with($process->command[1], 'predict.py') && in_array('--raster', $process->command, true));
    }
}
