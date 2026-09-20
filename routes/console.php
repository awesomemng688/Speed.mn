<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Jobs\PollServer;
use App\Models\Server;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('speedmn:poll', function () {
    Server::where('enabled', true)->pluck('id')->each(fn (int $id) => PollServer::dispatch($id));
    $this->info('Queued server polling jobs.');
})->purpose('Queue status checks for enabled Speed.mn servers');

Schedule::command('speedmn:poll')->everyMinute()->withoutOverlapping();
