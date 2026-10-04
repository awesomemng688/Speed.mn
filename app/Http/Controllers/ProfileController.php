<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Models\ServerStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        $user = Auth::user();
        $favoriteSort = $request->validate(['favorites_sort' => ['nullable', 'in:recent,name,players']])['favorites_sort'] ?? 'recent';
        $servers = Server::query()
            ->where('enabled', true)
            ->with('latestStatus')
            ->orderByDesc('updated_at')
            ->take(3)
            ->get();
        $favorites = $user->favoriteServers()
            ->where('servers.enabled', true)
            ->with('latestStatus')
            ->when($favoriteSort === 'name', fn ($query) => $query->orderBy('servers.name'))
            ->when($favoriteSort === 'players', fn ($query) => $query
                ->orderByDesc(ServerStatus::query()
                    ->select('players')
                    ->whereColumn('server_id', 'servers.id')
                    ->where('online', true)
                    ->where('created_at', '>=', ServerStatus::freshSince())
                    ->orderByDesc('created_at')
                    ->limit(1))
                ->orderBy('servers.name'))
            ->when($favoriteSort === 'recent', fn ($query) => $query->orderByDesc('server_favorites.created_at'))
            ->get();
        $favorites->each(fn (Server $server) => $server->setAttribute('is_favorited', true));
        $notifications = $user->notifications()->latest()->limit(8)->get();
        $unreadNotifications = $user->unreadNotifications()->count();

        $onlineServers = $servers->filter(fn (Server $server) => $server->latestStatus?->online)->count();
        $playersOnline = $servers->sum(fn (Server $server) => $server->latestStatus?->players ?? 0);
        $progress = $this->loadPlayerProgress((string) $user->steam_id);

        return view('profile', [
            'user' => $user,
            'servers' => $servers,
            'favorites' => $favorites,
            'favoriteSort' => $favoriteSort,
            'notifications' => $notifications,
            'unreadNotifications' => $unreadNotifications,
            'progress' => $progress,
            'networkSnapshot' => [
                'online_servers' => $onlineServers,
                'players_online' => $playersOnline,
            ],
        ]);
    }

    private function loadPlayerProgress(string $steamId): array
    {
        $empty = [
            'xp' => 0,
            'playtime' => 0,
            'level' => 1,
            'title' => 'Rookie',
            'progress_percent' => 0,
            'next_level_xp' => 500,
            'has_data' => false,
        ];

        if (!preg_match('/^\d{17}$/', $steamId)) {
            return $empty;
        }

        try {
            $schema = Schema::connection('rank_public_1');
            if (!$schema->hasTable('lvl_base')) {
                return $empty;
            }

            $columns = $schema->getColumnListing('lvl_base');
            $steamColumn = collect(['steam', 'steamid', 'steam_id'])->first(
                fn (string $column): bool => in_array($column, $columns, true)
            );
            if ($steamColumn === null) {
                return $empty;
            }

            $row = DB::connection('rank_public_1')
                ->table('lvl_base')
                ->where($steamColumn, $steamId)
                ->first();
            if ($row === null) {
                return $empty;
            }

            $playtime = max(0, (int) ($row->playtime ?? 0));
            // The profile progression is based on played minutes; `value` is
            // the rank plugin's separate score and must not inflate XP.
            $xp = $playtime;
            $levels = [
                ['min' => 0, 'next' => 500, 'title' => 'Rookie'],
                ['min' => 500, 'next' => 1000, 'title' => 'Player'],
                ['min' => 1000, 'next' => 2000, 'title' => 'Regular'],
                ['min' => 2000, 'next' => 4000, 'title' => 'Veteran'],
                ['min' => 4000, 'next' => null, 'title' => 'Elite'],
            ];
            $current = $levels[0];
            foreach ($levels as $level => $definition) {
                if ($xp >= $definition['min']) {
                    $current = $definition + ['number' => $level + 1];
                }
            }
            $range = ($current['next'] ?? $current['min'] + 1) - $current['min'];
            $percent = $current['next'] === null
                ? 100
                : min(100, max(0, (int) round(($xp - $current['min']) / $range * 100)));

            return [
                'xp' => $xp,
                'playtime' => $playtime,
                'level' => $current['number'] ?? 1,
                'title' => $current['title'],
                'progress_percent' => $percent,
                'next_level_xp' => $current['next'],
                'has_data' => true,
            ];
        } catch (\Throwable $exception) {
            Log::warning('Could not load player progress.', [
                'steam_id' => $steamId,
                'error' => $exception->getMessage(),
            ]);
            return $empty;
        }
    }
}
