<?php

namespace App\Console\Commands;

use App\Models\ServerStatus;
use Illuminate\Console\Command;

class PruneServerStatusesCommand extends Command
{
    protected $signature = 'speedmn:status-prune';

    protected $description = 'Remove server status history past its retention period';

    public function handle(): int
    {
        $retentionDays = min(90, max(30, (int) config('speedmn.status_retention_days', 90)));
        $cutoff = now()->subDays($retentionDays);
        $deleted = 0;

        do {
            $ids = ServerStatus::query()
                ->where('created_at', '<', $cutoff)
                ->orderBy('id')
                ->limit(5000)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $deleted += ServerStatus::query()->whereIn('id', $ids)->delete();
        } while ($ids->count() === 5000);

        $this->info("Removed {$deleted} server status row(s) older than {$retentionDays} days.");

        return self::SUCCESS;
    }
}