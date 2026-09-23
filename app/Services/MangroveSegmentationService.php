<?php

namespace App\Services;

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
        $weights = config('ml.model_path');

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

        $env = $this->pythonProcessEnvironment();
        $this->exposeWindowsSocketEnv($env);

        $pending = Process::timeout((int) config('ml.timeout', 180))->env($env);

        if (PHP_OS_FAMILY === 'Windows') {
            $pending = $pending->options([
                'create_process_group' => true,
            ]);
        }

        $result = $pending->run([
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
        ]);

        $output = trim($result->output());
        $lines = preg_split('/\r\n|\r|\n/', $output) ?: [];
        $jsonLine = '';
        foreach (array_reverse($lines) as $line) {
            if (str_starts_with(trim($line), '{')) {
                $jsonLine = trim($line);
                break;
            }
        }
        $payload = json_decode($jsonLine, true);

        if (! $result->successful() || ! is_array($payload) || empty($payload['ok'])) {
            $error = is_array($payload) ? ($payload['error'] ?? null) : null;
            $error ??= trim($result->errorOutput()) ?: trim($result->output()) ?: 'Segmentation failed.';

            throw new RuntimeException($error);
        }

        return $payload;
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
