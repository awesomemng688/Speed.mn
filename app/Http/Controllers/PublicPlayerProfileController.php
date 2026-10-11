<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Throwable;

class PublicPlayerProfileController extends Controller
{
    private const SOURCES = [
        'public-1' => ['label' => 'Нийтийн 1', 'connection' => 'rank_public_1', 'table' => 'rank_system_new', 'type' => 'public'],
        'public-2' => ['label' => 'Нийтийн 2', 'connection' => 'rank_public_2', 'table' => 'rank_system_new', 'type' => 'public'],
        'knife-1' => ['label' => 'Knife 1', 'connection' => 'rank_knife_1', 'table' => 'weapon_kills_new', 'type' => 'knife'],
        'knife-2' => ['label' => 'Knife 2', 'connection' => 'rank_knife_2', 'table' => 'weapon_kills_new', 'type' => 'knife'],
    ];

    public function show(string $steamId): View
    {
        abort_unless(preg_match('/^\d{17}$/', $steamId) === 1, 404);

        $accountId = (int) $steamId - 76561197960265728;
        $legacySteamId = 'STEAM_0:'.($accountId % 2).':'.intdiv($accountId, 2);
        $profiles = [];
        $name = null;

        foreach (self::SOURCES as $key => $source) {
            try {
                $schema = Schema::connection($source['connection']);
                if (! $schema->hasTable($source['table'])) {
                    continue;
                }

                $columns = $schema->getColumnListing($source['table']);
                $steamColumn = collect(['steamid', 'Steam ID', 'Steam', 'steam_id', 'steam_id64'])
                    ->first(fn (string $column): bool => in_array($column, $columns, true));
                if ($steamColumn === null) {
                    continue;
                }

                $player = DB::connection($source['connection'])
                    ->table($source['table'])
                    ->whereIn($steamColumn, [$steamId, $legacySteamId])
                    ->first();
                if ($player === null) {
                    continue;
                }

                $profiles[$key] = ['source' => $source, 'player' => $player, 'columns' => $columns];
                $name ??= $player->Nick ?? $player->name ?? $player->Player ?? null;
            } catch (Throwable $exception) {
                Log::warning('Could not load public player statistics.', [
                    'source' => $key,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return view('players.show', [
            'steamId' => $steamId,
            'name' => $name ?: 'Steam тоглогч',
            'profiles' => $profiles,
        ]);
    }
}
