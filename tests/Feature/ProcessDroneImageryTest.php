<?php

namespace Tests\Feature;

use App\Jobs\ProcessDroneImagery;
use App\Models\DroneImageryLayer;
use App\Services\GdalCommandRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProcessDroneImageryTest extends TestCase
{
    use RefreshDatabase;

    public function test_unreferenced_tiff_is_marked_failed_without_generating_tiles(): void
    {
        $environmentScript = config('gdal.qgis_env');
        if (! is_string($environmentScript) || ! is_file($environmentScript)) {
            $this->markTestSkipped('QGIS GDAL is not installed in this test environment.');
        }

        Storage::fake('local');
        $localDisk = Storage::disk('local');
        $sourceDirectory = storage_path('app/private/drone-imagery-test-inputs/'.uniqid('', true));
        File::ensureDirectoryExists($sourceDirectory);

        try {
            $sourcePath = $sourceDirectory.DIRECTORY_SEPARATOR.'unreferenced.tif';
            app(GdalCommandRunner::class)->run([
                'gdal_create',
                '-of',
                'GTiff',
                '-outsize',
                '16',
                '16',
                '-bands',
                '3',
                '-burn',
                '100',
                $sourcePath,
            ], $sourceDirectory, 30);

            $layer = DroneImageryLayer::create([
                'name' => 'Unreferenced raster test',
                'status' => 'queued',
                'source_type' => 'single',
            ]);
            $storedPath = "drone-imagery/uploads/{$layer->id}/unreferenced.tif";
            $localDisk->put($storedPath, file_get_contents($sourcePath));
            $layer->files()->create([
                'original_name' => 'unreferenced.tif',
                'stored_path' => $storedPath,
                'mime_type' => 'image/tiff',
                'file_size' => $localDisk->size($storedPath),
                'sort_order' => 0,
            ]);

            (new ProcessDroneImagery($layer->id))->handle(app(GdalCommandRunner::class));
            $layer->refresh();

            $this->assertSame('failed', $layer->status);
            $this->assertStringContainsString(
                'no usable CRS or georeferencing',
                $layer->error_message ?? ''
            );
            $this->assertNull($layer->tiles_path);
            $this->assertFalse($localDisk->exists("drone-imagery/tiles/{$layer->id}"));
        } finally {
            File::deleteDirectory($sourceDirectory);
            File::deleteDirectory(storage_path('app/private/drone-imagery-test-inputs'));
        }
    }

    public function test_georeferenced_batch_is_mosaicked_before_xyz_tiles_are_generated(): void
    {
        $environmentScript = config('gdal.qgis_env');
        if (! is_string($environmentScript) || ! is_file($environmentScript)) {
            $this->markTestSkipped('QGIS GDAL is not installed in this test environment.');
        }

        Storage::fake('local');
        config(['gdal.tile_zoom' => '12-14']);

        $localDisk = Storage::disk('local');
        $sourceDirectory = storage_path('app/private/drone-imagery-test-inputs/'.uniqid('', true));
        File::ensureDirectoryExists($sourceDirectory);

        try {
            $sourceFiles = [
                ['name' => 'north.tif', 'west' => '124', 'east' => '124.01'],
                ['name' => 'south.tif', 'west' => '124.01', 'east' => '124.02'],
            ];
            $layer = DroneImageryLayer::create([
                'name' => 'GDAL integration test',
                'status' => 'queued',
                'source_type' => 'batch',
            ]);

            foreach ($sourceFiles as $index => $source) {
                $sourcePath = $sourceDirectory.DIRECTORY_SEPARATOR.$source['name'];
                app(GdalCommandRunner::class)->run([
                    'gdal_create',
                    '-of',
                    'GTiff',
                    '-outsize',
                    '16',
                    '16',
                    '-bands',
                    '3',
                    '-burn',
                    '100',
                    '-a_srs',
                    'EPSG:4326',
                    '-a_ullr',
                    $source['west'],
                    '10',
                    $source['east'],
                    '9.99',
                    $sourcePath,
                ], $sourceDirectory, 30);

                $storedPath = "drone-imagery/uploads/{$layer->id}/{$source['name']}";
                $localDisk->put($storedPath, file_get_contents($sourcePath));
                $layer->files()->create([
                    'original_name' => $source['name'],
                    'stored_path' => $storedPath,
                    'mime_type' => 'image/tiff',
                    'file_size' => $localDisk->size($storedPath),
                    'sort_order' => $index,
                ]);
            }

            (new ProcessDroneImagery($layer->id))->handle(app(GdalCommandRunner::class));
            $layer->refresh();

            $this->assertSame('ready', $layer->status, $layer->error_message ?? '');
            $this->assertSame('EPSG:3857', $layer->metadata['target_crs']);
            $this->assertSame(2, $layer->metadata['source_count']);
            $this->assertGreaterThan(0, $layer->metadata['tile_count']);
            $this->assertLessThanOrEqual(124.0, $layer->bounds['west']);
            $this->assertGreaterThanOrEqual(124.02, $layer->bounds['east']);
            $localDisk->assertExists($layer->mosaic_path);
            $this->assertNotEmpty(File::allFiles($localDisk->path($layer->tiles_path)));
        } finally {
            File::deleteDirectory($sourceDirectory);
            File::deleteDirectory(storage_path('app/private/drone-imagery-test-inputs'));
        }
    }
}
