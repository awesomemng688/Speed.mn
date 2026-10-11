<?php

namespace App\Services;

use App\Models\Server;
use App\Models\ServerStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class NetworkStats
{
    /**
     * @return array{peak: int, peak_at: ?Carbon, uptime: ?float, checks: int, hourly: array<int, array{label: string, peak: int}>, servers: int, down: int}
     */
    public function summary(?Carbon $since = null): array
    {
        $since ??= now()->subDay();
        $driver = DB::connection()->getDriverName();
        $minute = $driver === 'sqlite' ? "strftime('%Y-%m-%d %H:%M', created_at)" : "DATE_FORMAT(created_at, '%Y-%m-%d %H:%i')";

        $minutes = ServerStatus::query()
            ->where('created_at', '>=', $since)
            ->where('online', true)
            ->selectRaw("{$minute} AS bucket, SUM(COALESCE(players, 0)) AS total")
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->get();

        $peakRow = $minutes->sortByDesc('total')->first();
        $hourly = [];
        foreach ($minutes as $row) {
            $local = Carbon::parse($row->bucket, config('app.timezone'))->timezone('Asia/Ulaanbaatar');
            $key = $local->format('m-d H:00');
            $hourly[$key] = max($hourly[$key] ?? 0, (int) $row->total);
        }

        $checks = ServerStatus::query()->where('created_at', '>=', $since)->count();
        $onlineChecks = ServerStatus::query()->where('created_at', '>=', $since)->where('online', true)->count();
        $freshSince = ServerStatus::freshSince();
        $enabled = Server::query()->where('enabled', true)->with('latestStatus')->get();

        return [
            'peak' => (int) ($peakRow->total ?? 0),
            'peak_at' => $peakRow ? Carbon::parse($peakRow->bucket, config('app.timezone'))->timezone('Asia/Ulaanbaatar') : null,
            'uptime' => $checks ? round($onlineChecks / $checks * 100, 2) : null,
            'checks' => $checks,
            'hourly' => collect($hourly)->map(fn ($peak, $label) => ['label' => $label, 'peak' => $peak])->values()->all(),
            'servers' => $enabled->count(),
            'down' => $enabled->filter(fn (Server $server) => $server->latestStatus
                && $server->latestStatus->created_at?->gte($freshSince)
                && ! $server->latestStatus->online)->count(),
        ];
    }
}
