<?php

namespace Database\Seeders;

use App\Models\Server;
use Illuminate\Database\Seeder;

class ServerSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            return;
        }

        Server::upsert([
            ['name' => 'MGL/KOR CS2 Competitive 5v5', 'game' => 'cs2', 'ip' => '115.68.216.208', 'port' => 27025, 'region' => 'MGL / KOR', 'country' => 'MN', 'max_players' => 12, 'query_type' => 'a2s', 'enabled' => true],
            ['name' => 'MGL/KOR CS2 Public', 'game' => 'cs2', 'ip' => '115.68.216.208', 'port' => 27026, 'region' => 'MGL / KOR', 'country' => 'MN', 'max_players' => 24, 'query_type' => 'a2s', 'enabled' => true],
            ['name' => 'Speed.mn CS 1.6', 'game' => 'cs16', 'ip' => '203.34.37.57', 'port' => 27015, 'region' => 'Mongolia', 'country' => 'MN', 'max_players' => 32, 'query_type' => 'a2s', 'enabled' => true],
        ], ['ip', 'port'], ['name', 'game', 'region', 'country', 'max_players', 'query_type', 'enabled']);
    }
}
