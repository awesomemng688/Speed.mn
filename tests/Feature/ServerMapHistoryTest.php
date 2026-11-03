<?php

namespace Tests\Feature;

use App\Models\Server;
use App\Models\ServerStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServerMapHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_server_page_lists_map_changes_and_vip_page_loads(): void
    {
        $server = Server::create([
            'name' => 'Map Test', 'game' => 'cs2', 'ip' => '127.0.0.1', 'port' => 27015,
            'max_players' => 10, 'enabled' => true,
        ]);

        foreach (['de_dust2' => 40, 'de_mirage' => 20] as $map => $minutesAgo) {
            ServerStatus::create([
                'server_id' => $server->id, 'online' => true, 'players' => 3, 'max_players' => 10,
                'map' => $map, 'created_at' => now()->subMinutes($minutesAgo),
            ]);
        }

        $this->get(route('servers.show', $server))
            ->assertOk()
            ->assertSee('Газрын зургийн түүх')
            ->assertSee('de_dust2')
            ->assertSee('de_mirage');

        $this->get('/vip')->assertOk()->assertSee('VIP');
    }
}
