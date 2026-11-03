<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Models\ServerStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
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
        $combat = $this->loadCombatStats((string) $user->steam_id);
        $recentActivity = $this->loadRecentRankActivity((int) $user->id);
        $achievements = [
            ['title' => 'Steam холбогдсон', 'description' => 'Бүртгэл баталгаажсан', 'unlocked' => true, 'icon' => '◈'],
            ['title' => '100 аллага', 'description' => '100 аллага', 'unlocked' => $combat['kills'] >= 100, 'icon' => '⚔'],
            ['title' => 'Толгойн ангууч', 'description' => '50 толгойн оносон', 'unlocked' => $combat['headshots'] >= 50, 'icon' => '◎'],
            ['title' => '10 цагийн тоглогч', 'description' => '10 цаг тоглосон', 'unlocked' => $combat['playtime_hours'] >= 10, 'icon' => '◷'],
            ['title' => 'Ахмад тоглогч', 'description' => '100 цаг тоглосон', 'unlocked' => $combat['playtime_hours'] >= 100, 'icon' => '★'],
        ];

        return view('profile', [
            'user' => $user,
            'servers' => $servers,
            'favorites' => $favorites,
            'favoriteSort' => $favoriteSort,
            'notifications' => $notifications,
            'unreadNotifications' => $unreadNotifications,
            'progress' => $progress,
            'combat' => $combat,
            'recentActivity' => $recentActivity,
            'achievements' => $achievements,
            'vip' => \App\Models\VipMember::query()->where('steam_id', $user->steam_id)->get()->filter->isActive(),
            'networkSnapshot' => [
                'online_servers' => $onlineServers,
                'players_online' => $playersOnline,
            ],
        ]);
    }

    private function loadRecentRankActivity(int $userId): array
    {
        $snapshots = DB::table('rank_daily_snapshots')
            ->where('user_id', $userId)
            ->where('source', 'public-1')
            ->where('snapshot_date', '>=', now()->subDays(7)->toDateString())
            ->orderBy('snapshot_date')
            ->get();
        $previousKills = null;

        return $snapshots->map(function ($snapshot) use (&$previousKills): array {
            $kills = (int) $snapshot->kills;
            $delta = $previousKills === null ? 0 : max(0, $kills - $previousKills);
            $previousKills = $kills;

            return [
                'date' => $snapshot->snapshot_date,
                'kills' => $delta,
                'total' => $kills,
            ];
        })->all();
    }

    private function loadCombatStats(string $steamId): array
    {
        $empty = ['kills' => 0, 'deaths' => 0, 'headshots' => 0, 'kd' => 0.0, 'hs_percent' => 0.0, 'playtime_hours' => 0, 'last_login' => null, 'has_data' => false];
        if (! preg_match('/^\d{17}$/', $steamId)) {
            return $empty;
        }

        return Cache::remember('profile.combat.'.sha1($steamId), now()->addMinute(), function () use ($steamId, $empty): array {
            try {
                $schema = Schema::connection('rank_public_1');
                if (! $schema->hasTable('rank_system_new')) {
                    return $empty;
                }
                $columns = $schema->getColumnListing('rank_system_new');
                $steamColumn = collect(['Steam ID', 'steamid', 'steam_id'])->first(fn ($name) => in_array($name, $columns, true));
                if (! $steamColumn) {
                    return $empty;
                }
                $steam2 = $this->steam2($steamId);
                $row = DB::connection('rank_public_1')->table('rank_system_new')
                    ->where(fn ($query) => $query->where($steamColumn, $steamId)->orWhere($steamColumn, $steam2))
                    ->first();
                if (! $row) {
                    return $empty;
                }
                $kills = (int) ($row->Kills ?? 0);
                $deaths = (int) ($row->Deaths ?? 0);
                $headshots = (int) ($row->Headshots ?? 0);
                $playtimeMinutes = max(0, (int) ($row->{'Played Time'} ?? 0));

                return [
                    'kills' => $kills,
                    'deaths' => $deaths,
                    'headshots' => $headshots,
                    'kd' => round($kills / max(1, $deaths), 2),
                    'hs_percent' => $kills > 0 ? round($headshots / $kills * 100, 1) : 0.0,
                    'playtime_hours' => (int) floor($playtimeMinutes / 60),
                    'last_login' => $row->{'Last Login'} ?? null,
                    'has_data' => true,
                ];
            } catch (\Throwable $exception) {
                Log::warning('Could not load combat stats for player profile.', [
                    'steam_id' => $steamId,
                    'error' => $exception->getMessage(),
                ]);

                return $empty;
            }
        });
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
                ->where(fn ($query) => $query->where($steamColumn, $steamId)->orWhere($steamColumn, $this->steam2($steamId)))
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

    private function steam2(string $steamId): string
    {
        $account = max(0, (int) $steamId - 76561197960265728);

        return 'STEAM_0:'.($account % 2).':'.intdiv($account, 2);
    }
}
