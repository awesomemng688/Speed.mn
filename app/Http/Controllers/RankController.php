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
        if ($source['type'] === 'knife') {
            $query = $connection
                ->table($source['table'])
                ->where('Knife', '>', 0);

            if ($search !== '') {
                $query->where('Nick', 'like', '%'.$search.'%');
            }

            $players = $query
                ->orderByDesc('Knife')
                ->orderByDesc('id')
                ->paginate(50)
                ->withQueryString();
        } else {
            $orderColumn = $column('XP') ?: ($column('points') ?: 'id');
            $query = $connection->table($source['table']);

            $query->when($search !== '', function ($query) use ($search, $column): void {
                $query->where($column('Nick') ?: ($column('name') ?: 'id'), 'like', '%'.$search.'%');
            });

            $players = $query
                ->orderByDesc($orderColumn)
                ->orderByDesc('id')
                ->paginate(50)
                ->withQueryString();
        }

        $avatars = $this->resolveAvatars($players->getCollection());

        return view('ranks.index', [
            'category' => $category,
            'source' => $source,
            'players' => $players,
            'avatars' => $avatars,
            'columns' => $columns,
            'column' => $column,
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
