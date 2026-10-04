<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Jobs\PollServer;
use App\Models\Server;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('speedmn:poll', function () {
    $serverIds = Server::where('enabled', true)->pluck('id');
    Cache::put('speedmn.poll.last_dispatched_at', now()->toIso8601String(), now()->addDay());
    $serverIds->each(fn (int $id) => PollServer::dispatch($id));
    $this->info("Queued {$serverIds->count()} server polling jobs.");
})->purpose('Queue status checks for enabled Speed.mn servers');

Schedule::command('speedmn:poll')->everyThirtySeconds()->withoutOverlapping();
