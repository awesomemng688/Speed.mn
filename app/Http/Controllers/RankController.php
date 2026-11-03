<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class RankController extends Controller
{
    private const SOURCES = [
        'public-1' => [
            'label' => 'Public 1',
            'connection' => 'rank_public_1',
            'table' => 'rank_system_new',
            'type' => 'public',
        ],
        'public-2' => [
            'label' => 'Public 2',
            'connection' => 'rank_public_2',
            'table' => 'rank_system_new',
            'type' => 'public',
        ],
        'knife-1' => [
            'label' => 'Knife 1',
            'connection' => 'rank_knife_1',
            'table' => 'weapon_kills_new',
            'type' => 'knife',
        ],
        'knife-2' => [
            'label' => 'Knife 2',
            'connection' => 'rank_knife_2',
            'table' => 'weapon_kills_new',
            'type' => 'knife',
        ],
    ];

    public function index(Request $request, string $category = 'public-1'): View
    {
        abort_unless(isset(self::SOURCES[$category]), 404);

        $source = self::SOURCES[$category];
        $connection = DB::connection($source['connection']);
        $columns = Schema::connection($source['connection'])->getColumnListing($source['table']);
        $column = static fn (string $name): ?string => in_array($name, $columns, true) ? $name : null;

        $search = trim((string) $request->query('search', ''));
        $period = in_array($request->query('period'), ['day', 'week', 'month'], true) ? $request->query('period') : 'all';
        $periodDays = ['day' => 1, 'week' => 7, 'month' => 30][$period] ?? null;
        $page = max(1, (int) $request->query('page', 1));
        $cacheKey = 'rank.page.'.$category.'.'.$period.'.'.sha1(mb_strtolower($search)).'.'.$page;
        $players = Cache::remember($cacheKey, now()->addSeconds(60), function () use ($source, $connection, $column, $search, $periodDays) {
            if ($source['type'] === 'knife') {
                $query = $connection
                    ->table($source['table'])
                    ->where('Knife', '>', 0);

                if ($search !== '') {
                    $query->where('Nick', 'like', '%'.$search.'%');
                }

                $query->orderByDesc('Knife');
                if ($column('id')) {
                    $query->orderByDesc('id');
                }

                return $query->paginate(50);
            }

            $orderColumn = $column('XP') ?: ($column('points') ?: 'id');
            $query = $connection->table($source['table']);

            if ($periodDays && $column('Last Login')) {
                $query->whereRaw("STR_TO_DATE(`Last Login`, '%m/%d/%Y - %H:%i:%s') >= ?", [now(config('app.timezone'))->subDays($periodDays)->format('Y-m-d H:i:s')]);
            }

            $query->when($search !== '', function ($query) use ($search, $column): void {
                $query->where($column('Nick') ?: ($column('name') ?: 'id'), 'like', '%'.$search.'%');
            });

            $query->orderByDesc($orderColumn);
            if ($column('id') && $orderColumn !== 'id') {
                $query->orderByDesc('id');
            }

            return $query->paginate(50);
        });
        $players->withQueryString();

        $avatars = $this->resolveAvatars($players->getCollection());

        return view('ranks.index', [
            'category' => $category,
            'source' => $source,
            'players' => $players,
            'period' => $period,
            'avatars' => $avatars,
            'columns' => $columns,
            'column' => $column,
        ]);
    }

    public function player(string $category, string $player): View
    {
        abort_unless(isset(self::SOURCES[$category]), 404);

        $source = self::SOURCES[$category];
        $row = DB::connection($source['connection'])
            ->table($source['table'])
            ->where('Player', $player)
            ->first();
        abort_if($row === null, 404);

        $stats = [];
        foreach ((array) $row as $key => $value) {
            if (! in_array($key, ['Player', 'IP', 'Flags', 'Avatar'], true)) {
                $stats[$key] = $value;
            }
        }

        return view('ranks.player', [
            'category' => $category,
            'source' => $source,
            'player' => $row,
            'stats' => $stats,
        ]);
    }

    private function resolveAvatars(\Illuminate\Support\Collection $players): array
    {
        $avatars = [];
        $steamIds = [];

        foreach ($players as $player) {
            $storedAvatar = trim((string) ($player->Avatar ?? ''));
            $steamId = $this->steam64((string) ($player->steamid ?? $player->{'Steam ID'} ?? $player->Steam ?? ''));
            $rawSteamId = trim((string) ($player->steamid ?? $player->{'Steam ID'} ?? $player->Steam ?? ''));
            $key = $steamId ?: strtolower(trim((string) ($player->Nick ?? $player->name ?? $player->Player ?? '')));

            if ($storedAvatar !== '') {
                $avatars[$key] = $storedAvatar;
                if ($rawSteamId !== '') {
                    $avatars[$rawSteamId] = $storedAvatar;
                }
            } elseif ($steamId !== '') {
                $steamIds[$steamId] = true;
            }
        }

        $apiKey = (string) config('services.steam.key');
        if ($apiKey === '' || $steamIds === []) {
            return $avatars;
        }

        foreach (array_chunk(array_keys($steamIds), 100) as $batch) {
            $cacheKey = 'steam.rank.avatars.'.sha1(implode(',', $batch));
            $profiles = Cache::remember($cacheKey, now()->addHours(6), function () use ($apiKey, $batch): array {
                $response = Http::timeout(8)->get(
                    'https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/',
                    ['key' => $apiKey, 'steamids' => implode(',', $batch)]
                );

                return $response->successful() ? (array) $response->json('response.players', []) : [];
            });

            foreach ($profiles as $profile) {
                if (!empty($profile['steamid']) && !empty($profile['avatarfull'])) {
                    $avatars[$profile['steamid']] = $profile['avatarfull'];
                }
            }
        }

        foreach ($players as $player) {
            $rawSteamId = trim((string) ($player->steamid ?? $player->{'Steam ID'} ?? $player->Steam ?? ''));
            $steamId = $this->steam64($rawSteamId);
            if ($rawSteamId !== '' && $steamId !== '' && isset($avatars[$steamId])) {
                $avatars[$rawSteamId] = $avatars[$steamId];
            }
        }

        return $avatars;
    }

    private function steam64(string $value): string
    {
        $value = trim($value);
        if (preg_match('/^\d{17}$/', $value)) {
            return $value;
        }

        if (preg_match('/^STEAM_[01]:(\d):(\d+)$/i', $value, $matches)) {
            return (string) (76561197960265728 + ((int) $matches[2] * 2) + (int) $matches[1]);
        }

        return '';
    }
}
