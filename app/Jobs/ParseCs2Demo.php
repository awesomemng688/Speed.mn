<?php

namespace App\Jobs;

use App\Models\DemoVideo;
use App\Services\Cs2DemoParser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ParseCs2Demo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 1860;

    public function __construct(public int $demoVideoId)
    {
        $this->onQueue('demo-parsing');
    }

    public function handle(Cs2DemoParser $parser): void
    {
        $demo = DemoVideo::find($this->demoVideoId);
        if (! $demo || $demo->media_type !== 'dem') {
            return;
        }

        $demo->update(['processing_status' => 'processing', 'processing_error' => null]);
        $analysisPath = $parser->parse($demo);
        $demo->update([
            'analysis_path' => $analysisPath,
            'processing_status' => 'ready',
            'processing_error' => null,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $demo = DemoVideo::find($this->demoVideoId);
        $demo?->update([
            'processing_status' => 'failed',
            'processing_error' => Str::limit($exception?->getMessage() ?: 'Demo parsing failed.', 2000),
        ]);

        Log::warning('CS2 demo parsing failed', [
            'demo_video_id' => $this->demoVideoId,
            'error' => $exception?->getMessage(),
        ]);
    }
}