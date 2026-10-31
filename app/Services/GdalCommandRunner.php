<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class GdalCommandRunner
{
    public function run(array $arguments, string $workingDirectory, int $timeout): string
    {
        $environmentScript = config('gdal.qgis_env');
        if (! is_string($environmentScript) || ! is_file($environmentScript)) {
            throw new RuntimeException('GDAL is not configured. Set GDAL_QGIS_ENV to the QGIS o4w_env.bat path.');
        }

        if (! is_dir($workingDirectory) && ! mkdir($workingDirectory, 0775, true) && ! is_dir($workingDirectory)) {
            throw new RuntimeException('Unable to create the GDAL processing directory.');
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
            $process = new Process([getenv('ComSpec') ?: 'cmd.exe', '/d', '/c', $scriptPath]);
            $process->setTimeout($timeout);
            $process->run();

            if (! $process->isSuccessful()) {
                $detail = trim($process->getErrorOutput()."\n".$process->getOutput());
                throw new RuntimeException(
                    'GDAL command failed with exit code '.$process->getExitCode().': '.mb_substr($detail, 0, 3000)
                );
            }

            return $process->getOutput();
        } catch (Throwable $exception) {
            if ($exception instanceof RuntimeException) {
                throw $exception;
            }

            throw new RuntimeException('Unable to execute GDAL: '.$exception->getMessage(), previous: $exception);
        } finally {
            if (is_file($scriptPath)) {
                unlink($scriptPath);
            }
        }
    }

    private function quoteWindowsArgument(string $argument): string
    {
        return '"'.str_replace('"', '""', $argument).'"';
    }
}
