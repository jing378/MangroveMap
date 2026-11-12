<?php

namespace App\Services;

use App\Models\AIModel;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class MangroveSegmentationService
{
    /**
     * @return array<string, mixed>
     */
    public function predict(string $imageAbsolutePath, string $overlayAbsolutePath): array
    {
        $python = config('ml.python');
        $script = config('ml.predict_script');
        $weights = $this->activeWeightsPath() ?? config('ml.model_path');

        if (! is_file((string) $python) && $python !== 'python' && $python !== 'python3') {
            throw new RuntimeException('Python interpreter not found. Create ml/.venv and install ml/requirements.txt.');
        }

        if (! is_file((string) $script)) {
            throw new RuntimeException('Prediction script is missing: '.$script);
        }

        if (! is_file((string) $weights)) {
            throw new RuntimeException('Model weights are missing: '.$weights);
        }

        if (! is_file($imageAbsolutePath)) {
            throw new RuntimeException('Image not found: '.$imageAbsolutePath);
        }

        return $this->runScript([
            (string) $python,
            (string) $script,
            '--image',
            $imageAbsolutePath,
            '--weights',
            $weights,
            '--overlay',
            $overlayAbsolutePath,
            '--size',
            (string) config('ml.input_size', 512),
        ], (int) config('ml.timeout', 180));
    }

    /**
     * Segment a large raster tile by tile, writing a 0/1 mask PNG with the same pixel grid.
     *
     * @return array<string, mixed>
     */
    public function predictRaster(string $rasterAbsolutePath, string $maskAbsolutePath): array
    {
        $python = config('ml.python');
        $script = config('ml.predict_script');
        $weights = $this->activeWeightsPath() ?? config('ml.model_path');

        if (! is_file((string) $python) && $python !== 'python' && $python !== 'python3') {
            throw new RuntimeException('Python interpreter not found. Create ml/.venv and install ml/requirements.txt.');
        }

        if (! is_file((string) $script)) {
            throw new RuntimeException('Prediction script is missing: '.$script);
        }

        if (! is_file((string) $weights)) {
            throw new RuntimeException('Model weights are missing: '.$weights);
        }

        if (! is_file($rasterAbsolutePath)) {
            throw new RuntimeException('Raster not found: '.$rasterAbsolutePath);
        }

        return $this->runScript([
            (string) $python,
            (string) $script,
            '--raster',
            $rasterAbsolutePath,
            '--weights',
            $weights,
            '--mask',
            $maskAbsolutePath,
            '--size',
            (string) config('ml.input_size', 512),
            '--overlap',
            (string) config('ml.drone.tile_overlap', 64),
            '--close',
            (string) config('ml.drone.close_iterations', 2),
        ], (int) config('ml.drone.timeout', 3600));
    }

    /**
     * Genus weights in use: the newest active "classification" model (.pt), else the configured file.
     * Null when genus suggestion is off or no weights exist, so callers can skip it.
     */
    public function genusWeightsPath(): ?string
    {
        if (! config('ml.genus.enabled', true)) {
            return null;
        }

        $registered = AIModel::query()
            ->where('model_type', 'classification')
            ->where('is_active', true)
            ->whereNotNull('model_path')
            ->orderByDesc('id')
            ->pluck('model_path')
            ->first(fn ($path) => is_string($path)
                && strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'pt'
                && is_file($path));

        if ($registered !== null) {
            return $registered;
        }

        $configured = (string) config('ml.genus.model_path');

        return $configured !== '' && is_file($configured) ? $configured : null;
    }

    /**
     * Which model delineates the mangrove extent of drone imagery: "yolo" (the canopy model, when its
     * weights exist) or "unet". See config ml.drone.extent_model.
     */
    public function extentModel(): string
    {
        $configured = strtolower(trim((string) config('ml.drone.extent_model', 'yolo')));

        return $configured === 'yolo' && $this->genusWeightsPath() !== null ? 'yolo' : 'unet';
    }

    /**
     * Mangrove extent of a raster as a 0/1 mask PNG (same pixel grid), with the configured extent model.
     *
     * With the canopy model, $yoloOptions are passed on to predictGenera (labels_out, canopies_out, bounds…)
     * so one run gives the extent, the genus suggestion and the canopies; the result then carries the
     * genus summary too. If that run fails, the U-Net takes over (logged). The result has 'extent_model'.
     *
     * @param  array<string, mixed>  $yoloOptions
     * @return array<string, mixed>
     */
    public function predictExtent(string $rasterAbsolutePath, string $maskAbsolutePath, array $yoloOptions = []): array
    {
        if ($this->extentModel() === 'yolo') {
            $weights = $this->genusWeightsPath();
            try {
                $result = $this->predictGenera($rasterAbsolutePath, null, (string) $weights, $yoloOptions['labels_out'] ?? null,
                    (int) ($yoloOptions['min_patch_px'] ?? 0), ['extent_out' => $maskAbsolutePath] + $yoloOptions);

                return ['extent_model' => 'yolo', 'extent_weights' => $weights] + $result;
            } catch (RuntimeException $exception) {
                Log::warning('Canopy model extent detection failed; falling back to the U-Net.', [
                    'raster' => $rasterAbsolutePath,
                    'exception' => $exception,
                ]);
            }
        }

        return ['extent_model' => 'unet'] + $this->predictRaster($rasterAbsolutePath, $maskAbsolutePath);
    }

    /**
     * Runs the canopy model over a raster: the share of each genus inside $maskAbsolutePath (a U-Net mask on
     * the same pixel grid) or, without a mask, inside the model's own extent (options extent_out).
     *
     * Options: extent_out (write the model's mangrove extent mask PNG there), canopies_out (write every
     * canopy as JSON there; needs bounds = EPSG:3857 corners [ulx, uly, lrx, lry]), pixel_m (ground metres
     * per pixel, for canopy areas), canopy_min_px, close_iterations.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function predictGenera(
        string $rasterAbsolutePath,
        ?string $maskAbsolutePath,
        string $weightsPath,
        ?string $labelsOutPath = null,
        int $minPatchPixels = 0,
        array $options = [],
    ): array {
        $python = config('ml.python');
        $script = config('ml.genus.script');

        if (! is_file((string) $python) && $python !== 'python' && $python !== 'python3') {
            throw new RuntimeException('Python interpreter not found. Create ml/.venv and install ml/requirements.txt.');
        }

        if (! is_file((string) $script)) {
            throw new RuntimeException('Genus prediction script is missing: '.$script);
        }

        if ($maskAbsolutePath === null && empty($options['extent_out'])) {
            throw new RuntimeException('The canopy model needs a mangrove mask or an extent output path.');
        }

        foreach (array_filter([$weightsPath, $rasterAbsolutePath, $maskAbsolutePath]) as $path) {
            if (! is_file($path)) {
                throw new RuntimeException('File not found: '.$path);
            }
        }

        $canopiesOut = $options['canopies_out'] ?? null;
        $bounds = $options['bounds'] ?? null;
        if ($canopiesOut !== null && (! is_array($bounds) || count($bounds) !== 4)) {
            throw new RuntimeException('Pinpointing canopies needs the raster bounds (EPSG:3857 corners).');
        }

        return $this->runScript([
            (string) $python,
            (string) $script,
            '--raster',
            $rasterAbsolutePath,
            ...($maskAbsolutePath !== null ? ['--mask', $maskAbsolutePath] : []),
            '--weights',
            $weightsPath,
            '--size',
            (string) config('ml.genus.input_size', 640),
            '--conf',
            (string) config('ml.genus.confidence', 0.1),
            '--prob-conf',
            (string) config('ml.genus.probability_confidence', 0.01),
            // The per-pixel genus map, kept so the owner's edited areas can be recounted (recountGenera).
            ...($labelsOutPath !== null ? ['--labels-out', $labelsOutPath] : []),
            '--min-patch-px',
            (string) max(0, $minPatchPixels),
            // The model's own mangrove extent (drone.extent_model = yolo).
            ...(! empty($options['extent_out']) ? [
                '--extent-out', (string) $options['extent_out'],
                '--extent-conf', (string) config('ml.genus.extent_confidence', 0.25),
                '--close', (string) (int) ($options['close_iterations'] ?? config('ml.drone.close_iterations', 2)),
            ] : []),
            // Every canopy pinpointed, for the map.
            ...($canopiesOut !== null ? [
                '--canopies-out', (string) $canopiesOut,
                '--bounds', ...array_map(fn ($value) => (string) (float) $value, array_values($bounds)),
                ...(isset($options['pixel_m']) ? ['--pixel-m', (string) (float) $options['pixel_m']] : []),
                '--canopy-min-px', (string) max(0, (int) ($options['canopy_min_px'] ?? 0)),
                '--canopy-max', (string) max(0, (int) config('ml.genus.canopy_max', 3000)),
                '--canopy-conf', (string) (float) config('ml.genus.canopy_confidence', 0.4),
                '--canopy-overlap', (string) (float) config('ml.genus.canopy_overlap', 0.5),
            ] : []),
        ], (int) config('ml.genus.timeout', 1800));
    }

    /**
     * Genus result for the owner's edited areas, counted from the genus map saved by predictGenera
     * (no model run, well under a second).
     *
     * @param  array<int, array<int, array{0: float, 1: float}>>  $lngLatRings
     * @param  array{east_m: float, north_m: float}  $offset  imagery offset the rings were drawn at
     * @return array<string, mixed>
     */
    public function recountGenera(string $labelsPath, string $metaPath, array $lngLatRings, array $offset): array
    {
        $python = config('ml.python');
        $script = config('ml.genus.script');

        if (! is_file((string) $python) && $python !== 'python' && $python !== 'python3') {
            throw new RuntimeException('Python interpreter not found. Create ml/.venv and install ml/requirements.txt.');
        }

        foreach ([(string) $script, $labelsPath, $metaPath] as $path) {
            if (! is_file($path)) {
                throw new RuntimeException('File not found: '.$path);
            }
        }

        $areasPath = tempnam(sys_get_temp_dir(), 'genus-areas-');
        if ($areasPath === false) {
            throw new RuntimeException('Could not create a temporary file for the genus recount.');
        }

        try {
            file_put_contents($areasPath, json_encode([
                'rings' => $lngLatRings,
                'offset' => ['east_m' => (float) $offset['east_m'], 'north_m' => (float) $offset['north_m']],
            ]));

            return $this->runScript([
                (string) $python,
                (string) $script,
                '--recount',
                $areasPath,
                '--labels',
                $labelsPath,
                '--meta',
                $metaPath,
                '--min-patch-m2',
                (string) (float) config('ml.genus.min_patch_m2', 2.0),
            ], (int) config('ml.genus.recount_timeout', 60));
        } finally {
            @unlink($areasPath);
        }
    }

    /**
     * The genus suggestion as stored in segmentation_stats.genus_suggestion, from a predict_genus.py result.
     *
     * @return array{genera: array<int, array<string, mixed>>, labeled_percent: float, probabilities: array<int, array<string, mixed>>, probability_basis: string}
     */
    public static function genusSuggestionFromResult(array $result): array
    {
        $genera = [];
        foreach ((array) ($result['genera'] ?? []) as $item) {
            $genus = trim((string) ($item['genus'] ?? ''));
            if ($genus === '') {
                continue;
            }
            $genera[] = [
                'genus' => ucfirst($genus),
                'share_percent' => round((float) ($item['share_percent'] ?? 0), 1),
                'mean_confidence' => round((float) ($item['mean_confidence'] ?? 0), 3),
                'detections' => (int) ($item['detections'] ?? 0),
            ];
        }

        // Experimental: a probability for every genus the model knows, shown even when it is unsure.
        $probabilities = [];
        foreach ((array) ($result['probabilities'] ?? []) as $item) {
            $genus = trim((string) ($item['genus'] ?? ''));
            if ($genus === '') {
                continue;
            }
            $probabilities[] = [
                'genus' => ucfirst($genus),
                'probability' => round((float) ($item['probability'] ?? 0), 4),
                'mean_confidence' => round((float) ($item['mean_confidence'] ?? 0), 3),
                'detections' => (int) ($item['detections'] ?? 0),
            ];
        }

        return [
            'genera' => $genera,
            // Share of the detected mangrove the model recognised at all.
            'labeled_percent' => round((float) ($result['labeled_percent'] ?? 0), 1),
            'probabilities' => $probabilities,
            // "pixels" = from the model's detections; "none" = it detected nothing, so the shares are equal.
            'probability_basis' => (string) ($result['probability_basis'] ?? 'none'),
        ];
    }

    /**
     * Check an uploaded genus checkpoint loads as a YOLO segmentation model.
     *
     * @return array<int, string> the class (genus) names
     */
    public function validateGenusWeights(string $weightsPath): array
    {
        return $this->inspectGenusWeights($weightsPath)['classes'];
    }

    /**
     * Validates a YOLO genus checkpoint and returns its classes plus the validation results that
     * Ultralytics stored in it at the end of training (null when the file has none):
     * mask mAP50 / mAP50-95 / precision / recall (0–1), dataset (e.g. the Roboflow export
     * "mangrove-canopy-2"), base model, epochs, image size and training date.
     *
     * @return array{classes: array<int, string>, training: array<string, mixed>|null}
     */
    public function inspectGenusWeights(string $weightsPath): array
    {
        $python = config('ml.python');
        $script = config('ml.genus.script');

        if (! is_file((string) $python) && $python !== 'python' && $python !== 'python3') {
            throw new RuntimeException('Python interpreter not found. Create ml/.venv and install ml/requirements.txt.');
        }

        if (! is_file((string) $script)) {
            throw new RuntimeException('Genus prediction script is missing: '.$script);
        }

        if (! is_file($weightsPath)) {
            throw new RuntimeException('Uploaded checkpoint could not be read.');
        }

        $result = $this->runScript([
            (string) $python,
            (string) $script,
            '--weights',
            $weightsPath,
            '--validate-weights',
        ], (int) config('ml.timeout', 180));

        return [
            'classes' => array_values(array_map('strval', (array) ($result['classes'] ?? []))),
            'training' => is_array($result['training'] ?? null) ? $result['training'] : null,
        ];
    }

    /**
     * @param  array<int, string>  $command
     * @return array<string, mixed>
     */
    private function runScript(array $command, int $timeout): array
    {
        $env = $this->pythonProcessEnvironment();
        $this->exposeWindowsSocketEnv($env);

        $pending = Process::timeout($timeout)->env($env);

        if (PHP_OS_FAMILY === 'Windows') {
            $pending = $pending->options([
                'create_process_group' => true,
            ]);
        }

        $result = $pending->run($command);
        $payload = $this->lastJsonLine($result->output());

        if (! $result->successful() || ! is_array($payload) || empty($payload['ok'])) {
            $error = is_array($payload) ? ($payload['error'] ?? null) : null;
            $error ??= trim($result->errorOutput()) ?: trim($result->output()) ?: 'Segmentation failed.';

            throw new RuntimeException($error);
        }

        return $payload;
    }

    /** Library warnings may precede the result, so read the last JSON object line. */
    private function lastJsonLine(string $output): mixed
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($output)) ?: [];
        foreach (array_reverse($lines) as $line) {
            if (str_starts_with(trim($line), '{')) {
                return json_decode(trim($line), true);
            }
        }

        return null;
    }

    public function validateWeights(string $weightsPath): void
    {
        $python = config('ml.python');
        $script = config('ml.predict_script');

        if (! is_file((string) $python) && $python !== 'python' && $python !== 'python3') {
            throw new RuntimeException('Python interpreter not found. Create ml/.venv and install ml/requirements.txt.');
        }

        if (! is_file((string) $script)) {
            throw new RuntimeException('Prediction script is missing: '.$script);
        }

        if (! is_file($weightsPath)) {
            throw new RuntimeException('Uploaded checkpoint could not be read.');
        }

        try {
            $this->runScript([
                (string) $python,
                (string) $script,
                '--weights',
                $weightsPath,
                '--validate-weights',
            ], (int) config('ml.timeout', 180));
        } catch (RuntimeException $exception) {
            $message = $exception->getMessage();
            throw new RuntimeException(
                $message !== 'Segmentation failed.' ? $message : 'Checkpoint is not compatible with the supported U-Net segmentation model.',
                previous: $exception
            );
        }
    }

    private function activeWeightsPath(): ?string
    {
        return AIModel::query()
            ->where('model_type', 'segmentation')
            ->where('is_active', true)
            ->whereNotNull('model_path')
            ->orderByDesc('id')
            ->pluck('model_path')
            ->first(fn ($path) => is_string($path)
                && strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'pth'
                && is_file($path));
    }

    /**
     * Apache/PHP on Windows often starts child processes without SystemRoot.
     * Python then fails importing asyncio/_overlapped with WinError 10106.
     *
     * @return array<string, string>
     */
    private function pythonProcessEnvironment(): array
    {
        $env = [];
        foreach (getenv() ?: [] as $key => $value) {
            if (is_string($key) && is_string($value) && $value !== '') {
                $env[$key] = $value;
            }
        }

        $systemRoot = $env['SystemRoot'] ?? $env['SYSTEMROOT'] ?? 'C:\\Windows';
        $system32 = $systemRoot.'\\System32';
        $path = $env['PATH'] ?? $env['Path'] ?? '';
        if (stripos($path, $system32) === false) {
            $path = $system32.';'.$systemRoot.';'.$system32.'\\Wbem;'.$path;
        }

        $env['SYSTEMROOT'] = $systemRoot;
        $env['SystemRoot'] = $systemRoot;
        $env['WINDIR'] = $env['WINDIR'] ?? $env['windir'] ?? $systemRoot;
        $env['windir'] = $env['WINDIR'];
        $env['PATH'] = $path;
        $env['Path'] = $path;
        $env['PATHEXT'] = $env['PATHEXT'] ?? '.COM;.EXE;.BAT;.CMD;.VBS';
        $env['COMSPEC'] = $env['COMSPEC'] ?? $system32.'\\cmd.exe';
        $env['TEMP'] = $env['TEMP'] ?? sys_get_temp_dir();
        $env['TMP'] = $env['TMP'] ?? sys_get_temp_dir();
        $env['PYTHONUNBUFFERED'] = '1';
        $env['PYTHONUTF8'] = '1';
        $env['PYTHONNOUSERSITE'] = '1';

        return $env;
    }

    /**
     * Symfony Process on Windows keeps only env vars that also exist in $_SERVER.
     *
     * @param  array<string, string>  $env
     */
    private function exposeWindowsSocketEnv(array $env): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return;
        }

        foreach (['SYSTEMROOT', 'SystemRoot', 'WINDIR', 'windir', 'PATH', 'Path'] as $key) {
            if (empty($env[$key])) {
                continue;
            }
            putenv($key.'='.$env[$key]);
            $_ENV[$key] = $env[$key];
            $_SERVER[$key] = $env[$key];
        }
    }
}
