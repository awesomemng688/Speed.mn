<?php

namespace App\Services;

use App\Models\DemoVideo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

class Cs2DemoParser
{
    public function parse(DemoVideo $demo): string
    {
        $python = (string) config('services.cs2_demo_parser.python');
        $entrypoint = (string) config('services.cs2_demo_parser.entrypoint');
        if ($python === '' || ! is_file($entrypoint)) {
            throw new RuntimeException('CS2 demo parser is not installed or configured.');
        }

        $disk = Storage::disk('local');
        $inputPath = $disk->path($demo->file_path);
        $analysisRelativePath = 'demo-analysis/'.$demo->id.'.json';
        $analysisPath = $disk->path($analysisRelativePath);
        $workingDirectory = storage_path('framework/demo-parser/'.$demo->id.'-'.Str::random(12));

        if (! is_file($inputPath)) {
            throw new RuntimeException('Uploaded demo file is missing from private storage.');
        }

        if (! is_dir($workingDirectory) && ! mkdir($workingDirectory, 0700, true) && ! is_dir($workingDirectory)) {
            throw new RuntimeException('Could not create an isolated parser working directory.');
        }

        $disk->makeDirectory('demo-analysis');
        try {
            $process = new Process([
                $python,
                $entrypoint,
                'parse',
                $inputPath,
                '--json-output',
                $analysisPath,
            ]);
            $process->setWorkingDirectory($workingDirectory);
            $process->setTimeout(max(60, (int) config('services.cs2_demo_parser.timeout_seconds', 1800)));
            $process->mustRun();

            $analysis = json_decode((string) file_get_contents($analysisPath), true, 512, JSON_THROW_ON_ERROR);
            foreach (['match_info', 'rounds', 'statistics', 'player_statistics'] as $requiredSection) {
                if (! array_key_exists($requiredSection, $analysis)) {
                    throw new RuntimeException('Parser output is missing required section: '.$requiredSection);
                }
            }

            return $analysisRelativePath;
        } finally {
            $this->removeDirectory($workingDirectory);
        }
    }

    private function removeDirectory(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        foreach (array_diff(scandir($directory) ?: [], ['.', '..']) as $entry) {
            $path = $directory.DIRECTORY_SEPARATOR.$entry;
            is_dir($path) ? $this->removeDirectory($path) : @unlink($path);
        }

        @rmdir($directory);
    }
}