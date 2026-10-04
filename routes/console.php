<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Jobs\PollServer;
use App\Models\Server;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('speedmn:poll', function () {
    $serverIds = Server::where('enabled', true)->pluck('id');
    $serverIds->each(fn (int $id) => PollServer::dispatch($id));
    try {
        Cache::put('speedmn.poll.last_dispatched_at', now()->toIso8601String(), now()->addDay());
    } catch (\Throwable $exception) {
        Log::warning('Could not store poll scheduler heartbeat', ['error' => $exception->getMessage()]);
    }
    $this->info("Queued {$serverIds->count()} server polling jobs.");
})->purpose('Queue status checks for enabled Speed.mn servers');

Artisan::command('speedmn:monitor {--test : Send a test alert to the configured Discord webhook}', function () {
    $staleServers = Server::where('enabled', true)
        ->where(fn ($query) => $query->whereNull('last_polled_at')->orWhere('last_polled_at', '<', now()->subMinutes(2)))
        ->count();
    $failedJobs = DB::table('failed_jobs')->where('failed_at', '>=', now()->subMinutes(5))->count();
    $problems = [];

    if ($staleServers > 0) {
        $problems[] = "{$staleServers} enabled server(s) have not been polled in 2 minutes";
    }
    if ($failedJobs > 0) {
        $problems[] = "{$failedJobs} queue job(s) failed in the last 5 minutes";
    }

    $state = $problems ? implode('|', array_map(fn ($problem) => str_contains($problem, 'server(s)') ? 'stale' : 'failed', $problems)) : 'healthy';
    $webhook = config('services.discord.admin_webhook');

    if (! $webhook) {
        if ($this->option('test')) {
            $this->error('DISCORD_ADMIN_WEBHOOK is not configured.');

            return 1;
        }

        $this->comment('DISCORD_ADMIN_WEBHOOK is not configured; health alert skipped.');
        return;
    }

    try {
        if ($this->option('test')) {
            Http::timeout(5)->post($webhook, [
                'content' => "Speed.mn Discord delivery test\nThis verifies stale-server and failed-job alert delivery.",
            ])->throw();
            $this->info('Discord test alert sent.');

            return;
        }

        if (Cache::get('speedmn.monitor.alert_state') === $state) {
            return;
        }

        $content = $problems
            ? "⚠️ Speed.mn monitoring alert\n• ".implode("\n• ", $problems)."\n".config('app.url').'/admin/servers'
            : "✅ Speed.mn monitoring recovered.\n".config('app.url').'/admin/servers';

        Http::timeout(5)->post($webhook, ['content' => $content])->throw();
        Cache::put('speedmn.monitor.alert_state', $state, now()->addDays(7));
    } catch (\Throwable $exception) {
        Log::warning('Could not send Speed.mn monitoring alert', ['error' => $exception->getMessage()]);
        $this->warn('Could not send monitoring alert; details were logged.');

        if ($this->option('test')) {
            return 1;
        }
    }
})->purpose('Notify administrators when server polling becomes stale or recovers');

Artisan::command('speedmn:audit-prune', function () {
    $retentionDays = max(1, (int) config('speedmn.audit_retention_days', 365));
    $deleted = DB::table('admin_audit_logs')
        ->where('created_at', '<', now()->subDays($retentionDays))
        ->delete();

    $this->info("Removed {$deleted} audit log(s) older than {$retentionDays} days.");
})->purpose('Remove administrator audit logs past the configured retention period');

Artisan::command('speedmn:deploy-smoke-record', function () {
    Cache::forever('speedmn.deploy.last_smoke_at', now()->toIso8601String());
    $this->info('Successful deploy smoke test recorded.');
})->purpose('Record a successful post-deploy HTTP smoke test');

Schedule::command('speedmn:poll')->everyThirtySeconds()->withoutOverlapping();
Schedule::command('speedmn:monitor')->everyMinute()->withoutOverlapping();
Schedule::command('speedmn:audit-prune')->dailyAt('02:15')->withoutOverlapping();
Schedule::command('speedmn:backup')->dailyAt('03:00')->withoutOverlapping();
Schedule::command('speedmn:status-prune')->dailyAt('03:30')->withoutOverlapping();
