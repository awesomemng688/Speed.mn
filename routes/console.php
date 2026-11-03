<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Jobs\PollServer;
use App\Models\Server;
use App\Models\Post;
use App\Models\User;
use App\Notifications\SiteActivityNotification;
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

Artisan::command('speedmn:tournament-reminders', function () {
    $events = Post::query()->where('type', 'tournament')->where('is_published', true)
        ->whereNull('tournament_reminder_sent_at')
        ->whereBetween('event_at', [now(), now()->addMinutes(15)])
        ->with('registrations.user')->get();

    foreach ($events as $event) {
        DB::transaction(function () use ($event): void {
            foreach ($event->registrations as $registration) {
                $registration->user?->notify(new SiteActivityNotification(
                    'Тэмцээн удахгүй эхэлнэ',
                    $event->title.' · '.($event->event_at?->timezone('Asia/Ulaanbaatar')->format('m-d H:i').' УБ' ?? ''),
                    route('news.show', $event),
                ));
            }
            $event->forceFill(['tournament_reminder_sent_at' => now()])->save();
        });
    }

    $this->info("Sent reminders for {$events->count()} tournament(s).");
})->purpose('Notify registered players 15 minutes before tournaments');

Artisan::command('speedmn:rank-up-notifications', function () {
    $users = User::query()->whereNotNull('steam_id')->get(['id', 'steam_id'])
        ->filter(fn (User $user) => preg_match('/^\d{17}$/', (string) $user->steam_id));
    if ($users->isEmpty()) {
        $this->info('No Steam-linked users to check.');
        return;
    }

    try {
        $schema = \Illuminate\Support\Facades\Schema::connection('rank_public_1');
        if (! $schema->hasTable('rank_system_new')) {
            $this->warn('Public rank table is unavailable.');
            return 1;
        }
        $columns = $schema->getColumnListing('rank_system_new');
        if (! in_array('Steam ID', $columns, true) || ! in_array('Level', $columns, true)) {
            $this->warn('Public rank table does not have the expected Steam ID and Level columns.');
            return 1;
        }

        $userBySteam = collect();
        $steamIdentifiers = [];
        foreach ($users as $user) {
            $account = max(0, (int) $user->steam_id - 76561197960265728);
            $steam2 = 'STEAM_0:'.($account % 2).':'.intdiv($account, 2);
            $userBySteam->put((string) $user->steam_id, $user);
            $userBySteam->put($steam2, $user);
            $steamIdentifiers[] = (string) $user->steam_id;
            $steamIdentifiers[] = $steam2;
        }
        $rows = DB::connection('rank_public_1')->table('rank_system_new')
            ->whereIn('Steam ID', $steamIdentifiers)
            ->get(['Steam ID', 'Level', 'Nick', 'Kills', 'Deaths', 'Headshots', 'XP']);
        foreach ($rows as $row) {
            $user = $userBySteam->get((string) $row->{'Steam ID'});
            $level = max(0, (int) $row->Level);
            if (! $user || $level < 1) {
                continue;
            }
            $snapshot = DB::table('rank_progress_snapshots')->where('user_id', $user->id)->where('source', 'public-1')->first();
            if ($snapshot && $level > (int) $snapshot->level) {
                $user->notify(new SiteActivityNotification(
                    'Чансаа ахилаа!',
                    ($row->Nick ?: $user->name).' · '.$snapshot->level.' → '.$level.'-р түвшин',
                    route('ranks.index', 'public-1'),
                ));
            }
            DB::table('rank_progress_snapshots')->updateOrInsert(
                ['user_id' => $user->id, 'source' => 'public-1'],
                ['level' => $level, 'updated_at' => now(), 'created_at' => $snapshot?->created_at ?? now()],
            );
            DB::table('rank_daily_snapshots')->updateOrInsert(
                ['user_id' => $user->id, 'source' => 'public-1', 'snapshot_date' => now()->toDateString()],
                [
                    'kills' => max(0, (int) $row->Kills),
                    'deaths' => max(0, (int) $row->Deaths),
                    'headshots' => max(0, (int) $row->Headshots),
                    'xp' => max(0, (int) $row->XP),
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }
    } catch (\Throwable $exception) {
        Log::error('Rank-up notification scan failed.', ['error' => $exception->getMessage()]);
        $this->error('Rank-up scan failed; details were logged.');
        return 1;
    }

    $this->info("Checked {$rows->count()} public rank record(s).");
})->purpose('Notify Steam-linked users when their public rank level increases');

Schedule::command('speedmn:poll')->everyThirtySeconds()->withoutOverlapping();
Schedule::command('speedmn:monitor')->everyMinute()->withoutOverlapping();
Schedule::command('speedmn:audit-prune')->dailyAt('02:15')->withoutOverlapping();
Schedule::command('speedmn:backup')->dailyAt('03:00')->withoutOverlapping();
Schedule::command('speedmn:status-prune')->dailyAt('03:30')->withoutOverlapping();
Schedule::command('speedmn:discord-players')->everyTenMinutes()->withoutOverlapping();
Schedule::command('speedmn:daily-report')->dailyAt('09:00')->timezone('Asia/Ulaanbaatar')->withoutOverlapping();
Schedule::command('speedmn:tournament-reminders')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('speedmn:rank-up-notifications')->everyFifteenMinutes()->withoutOverlapping();
