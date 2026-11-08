<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class GdalCommandRunner
{
    public function run(array $arguments, string $workingDirectory, int $timeout): string
    {
        if (! is_dir($workingDirectory) && ! mkdir($workingDirectory, 0775, true) && ! is_dir($workingDirectory)) {
            throw new RuntimeException('Unable to create the GDAL processing directory.');
        }

        if (PHP_OS_FAMILY === 'Windows') {
            return $this->runWithQgis($arguments, $workingDirectory, $timeout);
        }

        // Linux/macOS: GDAL (apt: gdal-bin, python3-gdal) is already on the PATH.
        return $this->execute(new Process($this->unixArguments($arguments)), $timeout);
    }

    /**
     * Windows: load the QGIS environment (o4w_env.bat) in a temporary .cmd script, then run the command.
     */
    private function runWithQgis(array $arguments, string $workingDirectory, int $timeout): string
    {
        $environmentScript = config('gdal.qgis_env');
        if (! is_string($environmentScript) || ! is_file($environmentScript)) {
            throw new RuntimeException('GDAL is not configured. Set GDAL_QGIS_ENV to the QGIS o4w_env.bat path.');
        }

        $scriptPath = $workingDirectory.DIRECTORY_SEPARATOR.'gdal-'.bin2hex(random_bytes(8)).'.cmd';
        $command = implode(' ', array_map($this->quoteWindowsArgument(...), $arguments));
        $script = implode("\r\n", [
            '@echo off',
            'call '.$this->quoteWindowsArgument($environmentScript).' >nul',
            'if errorlevel 1 exit /b 1',
            $command,
            'exit /b %errorlevel%',
            '',
        ]);

        if (file_put_contents($scriptPath, $script) === false) {
            throw new RuntimeException('Unable to prepare the GDAL command.');
        }

        try {
            return $this->execute(new Process([getenv('ComSpec') ?: 'cmd.exe', '/d', '/c', $scriptPath]), $timeout);
        } finally {
            if (is_file($scriptPath)) {
                unlink($scriptPath);
            }
        }
    }

    /**
     * Jobs call "python -m osgeo_utils...". Under QGIS that is QGIS's Python;
     * elsewhere it must be the Python that has GDAL installed.
     */
    private function unixArguments(array $arguments): array
    {
        if (($arguments[0] ?? null) === 'python') {
            $arguments[0] = (string) config('gdal.python', 'python3');
        }

        return $arguments;
    }

    private function execute(Process $process, int $timeout): string
    {
        try {
            $process->setTimeout($timeout);
            $process->run();
        } catch (Throwable $exception) {
            if ($exception instanceof RuntimeException) {
                throw $exception;
            }

            throw new RuntimeException('Unable to execute GDAL: '.$exception->getMessage(), previous: $exception);
        }

        if (! $process->isSuccessful()) {
            $detail = trim($process->getErrorOutput()."\n".$process->getOutput());
            throw new RuntimeException(
                'GDAL command failed with exit code '.$process->getExitCode().': '.mb_substr($detail, 0, 3000)
            );
        }

        return $process->getOutput();
    }

    private function quoteWindowsArgument(string $argument): string
    {
        return '"'.str_replace('"', '""', $argument).'"';
    }
}
