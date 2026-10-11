<?php

namespace App\Console\Commands;

use App\Services\NetworkStats;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

class DiscordDailyReportCommand extends Command
{
    protected $signature = 'speedmn:daily-report';

    protected $description = 'Send a 24-hour network summary to the admin Discord webhook';

    public function handle(NetworkStats $stats): int
    {
        $webhook = config('services.discord.admin_webhook');
        if (! $webhook) {
            $this->comment('DISCORD_ADMIN_WEBHOOK is not configured; daily report skipped.');

            return self::SUCCESS;
        }

        $summary = $stats->summary(now()->subDay());
        $content = "📊 Speed.mn өдрийн тайлан\n"
            .'• Оргил онлайн: '.$summary['peak'].($summary['peak_at'] ? ' ('.$summary['peak_at']->format('H:i').' УБ)' : '')."\n"
            .'• Uptime: '.($summary['uptime'] === null ? '—' : $summary['uptime'].'%')."\n"
            .'• Унасан сервер: '.$summary['down'].'/'.$summary['servers']."\n"
            .config('app.url').'/admin/dashboard';

        try {
            Http::timeout(5)->post($webhook, ['content' => $content])->throw();
        } catch (Throwable $exception) {
            $this->warn('Could not send daily report.');

            return self::FAILURE;
        }

        $this->info('Daily report sent.');

        return self::SUCCESS;
    }
}
