<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Models\ServerStatus;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Carbon;

class ServerController extends Controller
{
    public function index(Request $request, ?string $game = null): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
            'map' => ['nullable', 'string', 'max:80'],
            'status' => ['nullable', 'in:any,online,offline'],
            'players' => ['nullable', 'in:any,available,full'],
            'sort' => ['nullable', 'in:recommended,players,name'],
        ]);
        $freshSince = ServerStatus::freshSince();
        $servers = Server::query()
            ->where('enabled', true)
            ->when(in_array($game, ['cs2', 'cs16'], true), fn ($query) => $query->where('game', $game))
            ->when($filters['q'] ?? null, function ($query, string $term): void {
                $query->where(function ($query) use ($term): void {
                    $query->where('name', 'like', "%{$term}%")
                        ->orWhere('ip', 'like', "%{$term}%");

                    if (str_contains($term, ':')) {
                        [$ip, $port] = explode(':', $term, 2);
                        $query->orWhere(fn ($address) => $address->where('ip', 'like', "%{$ip}%")->where('port', 'like', "%{$port}%"));
                    } elseif (ctype_digit($term)) {
                        $query->orWhere('port', $term);
                    }
                });
            })
            ->when($filters['map'] ?? null, fn ($query, string $map) => $query->whereHas('latestStatus', fn ($status) => $status->where('map', 'like', "%{$map}%")))
            ->when(($filters['status'] ?? 'any') !== 'any', function ($query) use ($filters, $freshSince): void {
                $query->whereHas('latestStatus', fn ($status) => $status
                    ->where('created_at', '>=', $freshSince)
                    ->where('online', $filters['status'] === 'online'));
            })
            ->when(($filters['players'] ?? 'any') !== 'any', function ($query) use ($filters, $freshSince): void {
                $query->whereHas('latestStatus', function ($status) use ($filters, $freshSince): void {
                    $status->where('online', true)->where('created_at', '>=', $freshSince);
                    $capacity = 'COALESCE(NULLIF(server_statuses.max_players, 0), servers.max_players)';
                    $status->whereRaw($filters['players'] === 'full'
                        ? "{$capacity} > 0 AND server_statuses.players >= {$capacity}"
                        : "{$capacity} > 0 AND server_statuses.players < {$capacity}");
                });
            })
            ->with('latestStatus')
            ->when($request->user(), fn ($query, $user) => $query->withExists([
                'favoritedBy as is_favorited' => fn ($favorites) => $favorites->where('users.id', $user->id),
            ]))
            ->when(($filters['sort'] ?? 'recommended') === 'players', function ($query) use ($freshSince): void {
                $query->orderByDesc(ServerStatus::query()
                    ->select('players')
                    ->whereColumn('server_id', 'servers.id')
                    ->where('created_at', '>=', $freshSince)
                    ->orderByDesc('created_at')
                    ->limit(1));
            }, function ($query) use ($filters, $freshSince): void {
                if (($filters['sort'] ?? 'recommended') === 'name') {
                    $query->orderBy('name');
                    return;
                }

                $fullRank = ServerStatus::query()
                    ->selectRaw('CASE WHEN server_statuses.online = 1 AND server_statuses.created_at >= ? AND server_statuses.players >= COALESCE(NULLIF(server_statuses.max_players, 0), servers.max_players) THEN 1 ELSE 0 END', [$freshSince])
                    ->whereColumn('server_id', 'servers.id')
                    ->orderByDesc('created_at')
                    ->limit(1);
                $query->orderByDesc($fullRank)
                    ->orderByDesc(ServerStatus::query()
                        ->select('players')
                        ->whereColumn('server_id', 'servers.id')
                        ->where('created_at', '>=', $freshSince)
                        ->orderByDesc('created_at')
                        ->limit(1))
                    ->orderBy('name');
            })
            ->orderBy('game')
            ->paginate(12)
            ->withQueryString();

        return view('servers.index', compact('servers', 'game', 'filters'));
    }

    public function show(Request $request, Server $server): View
    {
        abort_unless($server->enabled, 404);
        $period = $request->validate(['period' => ['nullable', 'in:24h,7d,30d']])['period'] ?? '24h';
        $server->load('latestStatus');
        $periodDays = match ($period) {
            '7d' => 7,
            '30d' => 30,
            default => 1,
        };
        $periodStart = Carbon::now()->subDays($periodDays);

        if ($period === '24h') {
            $history = $server->statuses()
                ->where('created_at', '>=', $periodStart)
                ->oldest()
                ->get();
            $totalChecks = $history->count();
            $onlineHistory = $history->where('online', true);
            $uptime = $totalChecks ? round($onlineHistory->count() / $totalChecks * 100, 2) : null;
            $averagePlayers = $onlineHistory->isNotEmpty() ? round($onlineHistory->avg('players'), 1) : null;
            $peakPlayers = $onlineHistory->max('players');
            $offlineHistory = $history->where('online', false)->values();
        } else {
            $history = $server->statuses()
                ->where('created_at', '>=', $periodStart)
                ->selectRaw('DATE(created_at) AS bucket')
                ->selectRaw('COUNT(*) AS checks')
                ->selectRaw('SUM(CASE WHEN online = 1 THEN 1 ELSE 0 END) AS online_checks')
                ->selectRaw('AVG(CASE WHEN online = 1 THEN players END) AS average_players')
                ->selectRaw('MAX(CASE WHEN online = 1 THEN players END) AS peak_players')
                ->groupByRaw('DATE(created_at)')
                ->orderBy('bucket')
                ->get();
            $totalChecks = (int) $history->sum('checks');
            $onlineChecks = (int) $history->sum('online_checks');
            $uptime = $totalChecks ? round($onlineChecks / $totalChecks * 100, 2) : null;
            $averagePlayers = $onlineChecks
                ? round($history->sum(fn ($point) => (float) $point->average_players * (int) $point->online_checks) / $onlineChecks, 1)
                : null;
            $peakPlayers = $history->max('peak_players');
            $offlineHistory = $server->statuses()
                ->where('created_at', '>=', $periodStart)
                ->where('online', false)
                ->latest('created_at')
                ->limit(10)
                ->get();
        }

        if ($request->user()) {
            $server->loadExists(['favoritedBy as is_favorited' => fn ($favorites) => $favorites->where('users.id', $request->user()->id)]);
        }

        return view('servers.show', compact('server', 'history', 'uptime', 'averagePlayers', 'peakPlayers', 'totalChecks', 'offlineHistory', 'period', 'periodDays'));
    }
}
