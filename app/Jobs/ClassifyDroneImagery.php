<?php

namespace App\Jobs;

use App\Jobs\Concerns\PreparesAiRaster;
use App\Models\DroneImageryLayer;
use App\Services\GdalCommandRunner;
use App\Services\MangroveSegmentationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Genus classification: runs the canopy model (YOLO, model_type=classification) over a drone mosaic and
 * counts the genus inside the layer's current mangrove areas, the AI delineation or the owner's corrections.
 * Separate from the delineation (SegmentDroneImagery): own status (classification_status), own results
 * (classification_stats: genus_suggestion, canopy_count, canopies_by_genus), and it can be re-run on its
 * own, e.g. after a better genus model is uploaded, without touching the areas.
 *
 * Keeps the per-pixel genus map of the whole mosaic (genus_labels.png + .json), so later area edits are
 * recounted without running the model (DroneImageryController::updateSegmentation), and every canopy found
 * inside the areas (canopies.json). If the owner saves edited areas while it runs, the result is recounted
 * for those at the end.
 */
class ClassifyDroneImagery implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, PreparesAiRaster, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 7200;

    public function __construct(public int $layerId)
    {
        $this->onConnection('drone_imagery')->onQueue('drone-imagery');
        $this->afterCommit();
    }

    /**
     * Queues the genus classification for a layer whose mangrove areas are ready. Without a genus model
     * nothing is queued (and no status shown). A failure to queue never affects the areas.
     */
    public static function queueFor(DroneImageryLayer $layer): bool
    {
        if (app(MangroveSegmentationService::class)->genusWeightsPath() === null) {
            return false;
        }

        try {
            $layer->update([
                'classification_status' => 'queued',
                'classification_stats' => null,
                'classification_error' => null,
            ]);
            self::dispatch($layer->id);

            return true;
        } catch (Throwable $exception) {
            $layer->update([
                'classification_status' => 'failed',
                'classification_error' => 'Unable to queue genus detection.',
            ]);
            Log::error('Unable to queue drone imagery genus classification.', [
                'layer_id' => $layer->id,
                'exception' => $exception,
            ]);

            return false;
        }
    }

    public function handle(GdalCommandRunner $gdal, MangroveSegmentationService $segmentation): void
    {
        $layer = DroneImageryLayer::find($this->layerId);
        if ($layer === null) {
            return; // deleted while queued
        }

        $disk = Storage::disk('local');
        $workDirectory = storage_path("app/private/drone-imagery/classification-work/{$layer->id}");

        // The old results no longer apply once this run starts.
        $disk->delete([$layer->genusLabelsPath(), $layer->genusLabelsMetaPath(), $layer->canopiesPath()]);
        $layer->update([
            'classification_status' => 'processing',
            'classification_stats' => null,
            'classification_error' => null,
        ]);

        try {
            $weights = $segmentation->genusWeightsPath();
            if ($weights === null) {
                throw new RuntimeException('No genus model is installed.');
            }

            [$rings, $areasOffset] = $this->currentAreas($layer);
            $areasVersion = $layer->segmented_at?->toIso8601String();

            File::ensureDirectoryExists($workDirectory);
            $raster = $this->prepareAiRaster($gdal, $layer, $workDirectory);
            $areasPath = $workDirectory.DIRECTORY_SEPARATOR.'areas.json';
            file_put_contents($areasPath, json_encode([
                'rings' => $rings,
                'offset' => $areasOffset,
                'raster' => ['bounds' => $raster['bounds'], 'latitude' => $raster['latitude'], 'offset' => $raster['offset']],
            ]));

            $options = $this->canopyModelOptions($workDirectory, $raster) + ['areas' => $areasPath];
            $result = $segmentation->predictGenera(
                $raster['input_path'], null, $weights, $options['labels_out'], $options['min_patch_px'], $options
            );

            $suggestion = $rings === [] ? $this->emptySuggestion() : MangroveSegmentationService::genusSuggestionFromResult($result);
            if ($this->storeGenusMap($layer, $result, $options, $raster)) {
                $suggestion['recountable'] = true;
            }
            $canopies = $this->storeCanopies($layer, $options);

            $layer->refresh();
            if ($layer->segmentation_status === 'ready' && $layer->segmented_at?->toIso8601String() !== $areasVersion) {
                // The owner saved other areas meanwhile: count the genus inside those instead.
                $suggestion = $this->recountForCurrentAreas($layer, $suggestion, $segmentation);
            }

            $layer->update([
                'classification_status' => 'ready',
                'classification_stats' => [
                    'genus_suggestion' => $suggestion,
                    'canopy_count' => $canopies['count'] ?? null,
                    'canopies_by_genus' => $canopies['by_genus'] ?? null,
                    // Position nudge contained in the canopy coordinates, so the editor can line them up.
                    'offset' => $raster['offset'],
                    'resolution_m' => round($raster['pixel_m'], 3),
                    'device' => $result['device'] ?? null,
                ],
                'classification_error' => null,
                'classified_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $message = mb_substr($exception->getMessage(), 0, 2000);
            $layer->update([
                'classification_status' => 'failed',
                // Shown like before the split: the editor says there is no AI suggestion.
                'classification_stats' => ['genus_suggestion' => ['error' => mb_substr($message, 0, 500)]],
                'classification_error' => $message,
            ]);
            Log::error('Drone imagery genus classification failed.', [
                'layer_id' => $layer->id,
                'exception' => $exception,
            ]);
        } finally {
            File::deleteDirectory($workDirectory);
        }
    }

    /**
     * The layer's current mangrove areas as [lng, lat] rings, and the offset they sit at.
     *
     * @return array{0: array<int, array<int, array{0: float, 1: float}>>, 1: array{east_m: float, north_m: float}}
     */
    private function currentAreas(DroneImageryLayer $layer): array
    {
        $disk = Storage::disk('local');
        if ($layer->segmentation_status !== 'ready'
            || $layer->segmentation_path !== "drone-imagery/processed/{$layer->id}/ai_extent.json"
            || ! $disk->exists($layer->segmentation_path)) {
            throw new RuntimeException('Detect the mangrove areas before classifying the genus.');
        }

        $extent = json_decode((string) $disk->get($layer->segmentation_path), true);
        $rings = [];
        foreach ((array) ($extent['features'] ?? []) as $feature) {
            $coords = is_array($feature) ? ($feature['coords'] ?? null) : null;
            if (! is_array($coords) || count($coords) < 3) {
                continue;
            }
            // Stored in Leaflet [lat, lng] order.
            $rings[] = array_map(fn (array $point) => [(float) $point[1], (float) $point[0]], $coords);
        }

        $offset = $layer->segmentation_stats['offset'] ?? DroneImageryLayer::EMPTY_EDITS['offset'];

        return [$rings, ['east_m' => (float) ($offset['east_m'] ?? 0), 'north_m' => (float) ($offset['north_m'] ?? 0)]];
    }

    /** Where the canopy model writes its outputs and its size thresholds for this raster. */
    private function canopyModelOptions(string $workDirectory, array $raster): array
    {
        $pixelM = max(0.001, (float) $raster['pixel_m']);
        $pixelsFor = fn (float $groundM2) => (int) ceil(max(0.0, $groundM2) / ($pixelM ** 2));

        return [
            'labels_out' => $workDirectory.DIRECTORY_SEPARATOR.'genus_labels.png',
            'canopies_out' => $workDirectory.DIRECTORY_SEPARATOR.'canopies.json',
            'bounds' => array_map('floatval', $raster['bounds']),
            'pixel_m' => $pixelM,
            // Smallest patch of one genus that counts, in pixels of this raster (edge slivers are ignored).
            'min_patch_px' => $pixelsFor((float) config('ml.genus.min_patch_m2', 2.0)),
            // Smallest canopy worth pinpointing.
            'canopy_min_px' => $pixelsFor((float) config('ml.genus.canopy_min_area_m2', 1.0)),
        ];
    }

    /** Keeps the per-pixel genus map and where it sits, for recounts. False when the run made none. */
    private function storeGenusMap(DroneImageryLayer $layer, array $result, array $options, array $raster): bool
    {
        $labelsWorkPath = (string) ($options['labels_out'] ?? '');
        if ($labelsWorkPath === '' || ! is_file($labelsWorkPath)) {
            return false;
        }

        $disk = Storage::disk('local');
        $disk->put($layer->genusLabelsPath(), (string) file_get_contents($labelsWorkPath));
        $disk->put($layer->genusLabelsMetaPath(), json_encode([
            'classes' => array_values((array) ($result['classes'] ?? [])),
            'bounds' => array_map('floatval', $raster['bounds']),
            'latitude' => (float) $raster['latitude'],
            'offset' => $raster['offset'],
            'conf' => (float) config('ml.genus.confidence', 0.1),
            'prob_conf' => (float) config('ml.genus.probability_confidence', 0.01),
            'min_patch_px' => (int) ($options['min_patch_px'] ?? 0),
        ], JSON_UNESCAPED_SLASHES));

        return true;
    }

    /**
     * Keeps the pinpointed canopies (canopies.json) with the layer and returns their summary.
     *
     * @return array{count: int, kept: int, by_genus: array<string, int>}|null
     */
    private function storeCanopies(DroneImageryLayer $layer, array $options): ?array
    {
        $workPath = (string) ($options['canopies_out'] ?? '');
        if ($workPath === '' || ! is_file($workPath)) {
            return null;
        }

        $contents = (string) file_get_contents($workPath);
        $decoded = json_decode($contents, true);
        if (! is_array($decoded) || ! is_array($decoded['canopies'] ?? null)) {
            return null;
        }

        Storage::disk('local')->put($layer->canopiesPath(), $contents);

        return [
            'count' => (int) ($decoded['count'] ?? count($decoded['canopies'])),
            'kept' => (int) ($decoded['kept'] ?? count($decoded['canopies'])),
            'by_genus' => array_map('intval', (array) ($decoded['by_genus'] ?? [])),
        ];
    }

    /** The suggestion for the areas saved while the model ran, recounted from the new genus map. */
    private function recountForCurrentAreas(DroneImageryLayer $layer, array $suggestion, MangroveSegmentationService $segmentation): array
    {
        try {
            [$rings, $offset] = $this->currentAreas($layer);
        } catch (Throwable) {
            return $suggestion;
        }
        if ($rings === []) {
            return [...$this->emptySuggestion(), 'recountable' => $suggestion['recountable'] ?? false];
        }

        $disk = Storage::disk('local');
        if (! $disk->exists($layer->genusLabelsPath()) || ! $disk->exists($layer->genusLabelsMetaPath())) {
            return $suggestion;
        }

        try {
            $result = $segmentation->recountGenera(
                $disk->path($layer->genusLabelsPath()), $disk->path($layer->genusLabelsMetaPath()), $rings, $offset
            );
        } catch (Throwable $exception) {
            Log::warning('Recounting the genus for areas edited during classification failed.', [
                'layer_id' => $layer->id,
                'exception' => $exception,
            ]);

            return [...$suggestion, 'recount_failed' => true];
        }

        return [
            ...MangroveSegmentationService::genusSuggestionFromResult($result),
            'recountable' => true,
            'recounted_at' => now()->toIso8601String(),
        ];
    }

    /** No mangrove areas: nothing to suggest a genus for (the map is still kept for areas drawn later). */
    private function emptySuggestion(): array
    {
        return ['genera' => [], 'labeled_percent' => 0.0, 'probabilities' => [], 'probability_basis' => 'none'];
    }
}
