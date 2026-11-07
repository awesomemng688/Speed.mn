<?php

namespace Tests\Feature;

use App\Models\Server;
use App\Models\ServerStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServerListingTest extends TestCase
{
    use RefreshDatabase;

    public function test_server_index_page_returns_successful_response(): void
    {
        $this->get('/servers')
            ->assertOk()
            ->assertViewIs('servers.index')
            ->assertSee('Бүх сервер');
    }

    public function test_cs2_game_filter_page(): void
    {
        $this->get('/servers/cs2')
            ->assertOk()
            ->assertViewIs('servers.index')
            ->assertSee('CS2 серверүүд');
    }

    public function test_cs16_game_filter_page(): void
    {
        $this->get('/servers/cs16')
            ->assertOk()
            ->assertViewIs('servers.index')
            ->assertSee('CS 1.6 серверүүд');
    }

    public function test_search_by_server_name(): void
    {
        $server = $this->createServer('Test Server Alpha', '198.51.100.10');
        $this->createStatus($server, 5, 20);

        $this->get('/servers?q=Alpha')
            ->assertOk()
            ->assertSee('Test Server Alpha');
    }

    public function test_search_by_ip(): void
    {
        $server = $this->createServer('Some Server', '198.51.100.20');
        $this->createStatus($server, 5, 20);

        $this->get('/servers?q=198.51.100.20')
            ->assertOk()
            ->assertSee('Some Server');
    }

    public function test_filter_by_map(): void
    {
        $server1 = $this->createServer('Mirage Server', '198.51.100.30');
        $this->createStatus($server1, 5, 20, 'de_mirage');

        $server2 = $this->createServer('Dust2 Server', '198.51.100.31');
        $this->createStatus($server2, 5, 20, 'de_dust2');

        $this->get('/servers?map=mirage')
            ->assertOk()
            ->assertSee('Mirage Server')
            ->assertDontSee('Dust2 Server');
    }

    public function test_filter_by_status_online(): void
    {
        $online = $this->createServer('Online Server', '198.51.100.40');
        $this->createStatus($online, 10, 20);

        $offline = $this->createServer('Offline Server', '198.51.100.41');
        $this->createStatus($offline, 0, 20, 'de_dust2', false);

        $this->get('/servers?status=online')
            ->assertOk()
            ->assertSee('Online Server')
            ->assertDontSee('Offline Server');
    }

    public function test_filter_by_status_offline(): void
    {
        $online = $this->createServer('Online Server', '198.51.100.42');
        $this->createStatus($online, 10, 20);

        $offline = $this->createServer('Offline Server', '198.51.100.43');
        $this->createStatus($offline, 0, 20, 'de_dust2', false);

        $this->get('/servers?status=offline')
            ->assertOk()
            ->assertSee('Offline Server')
            ->assertDontSee('Online Server');
    }

    public function test_filter_by_players_available(): void
    {
        $full = $this->createServer('Full Server', '198.51.100.50');
        $this->createStatus($full, 20, 20);

        $available = $this->createServer('Available Server', '198.51.100.51');
        $this->createStatus($available, 5, 20);

        $this->get('/servers?players=available')
            ->assertOk()
            ->assertSee('Available Server')
            ->assertDontSee('Full Server');
    }

    public function test_sort_by_players(): void
    {
        $low = $this->createServer('Low Players', '198.51.100.60');
        $this->createStatus($low, 2, 20);

        $high = $this->createServer('High Players', '198.51.100.61');
        $this->createStatus($high, 18, 20);

        $this->get('/servers?sort=players')
            ->assertOk()
            ->assertSeeInOrder(['High Players', 'Low Players']);
    }

    public function test_server_detail_page(): void
    {
        $server = $this->createServer('Detail Server', '198.51.100.70');
        $this->createStatus($server, 10, 20, 'de_inferno');

        $this->get('/server/'.$server->id)
            ->assertOk()
            ->assertViewIs('servers.show')
            ->assertSee('Detail Server')
            ->assertSee('198.51.100.70')
            ->assertSee('de_inferno')
            ->assertSee('ОНЛАЙН');
    }

    public function test_server_detail_shows_offline_status(): void
    {
        $server = $this->createServer('Offline Detail', '198.51.100.71');
        $this->createStatus($server, 0, 20, 'de_dust2', false);

        $this->get('/server/'.$server->id)
            ->assertOk()
            ->assertSee('ОФЛАЙН');
    }

    public function test_server_detail_404_for_disabled(): void
    {
        $server = Server::create([
            'name' => 'Disabled Server',
            'game' => 'cs2',
            'ip' => '198.51.100.80',
            'port' => 27015,
            'max_players' => 20,
            'enabled' => false,
        ]);

        $this->get('/server/'.$server->id)
            ->assertNotFound();
    }

    public function test_cs16_category_grouping(): void
    {
        $public1 = $this->createServer('Public 1 Server', '198.51.100.90', 'cs16');
        $this->createStatus($public1, 5, 20);
        $public1->update(['category' => 'public-1']);

        $public2 = $this->createServer('Public 2 Server', '198.51.100.91', 'cs16');
        $this->createStatus($public2, 5, 20);
        $public2->update(['category' => 'public-2']);

        $this->get('/servers/cs16')
            ->assertOk()
            ->assertSee('Public 1 сервер')
            ->assertSee('Public 2 сервер');
    }

    public function test_pagination_works(): void
    {
        // Create 16 servers (pagination is 15 per page)
        for ($i = 1; $i <= 16; $i++) {
            $server = $this->createServer("Server {$i}", "198.51.100.{$i}");
            $this->createStatus($server, 5, 20);
        }

        $this->get('/servers')
            ->assertOk()
            ->assertSee('Server 1')
            ->assertSee('Server 15');

        $this->get('/servers?page=2')
            ->assertOk()
            ->assertSee('Server 16');
    }

    public function test_empty_search_message(): void
    {
        $this->get('/servers?q=nonexistentserverxyz')
            ->assertOk()
            ->assertSee('Шүүлтэд тохирох сервер олдсонгүй.');
    }

    private function createServer(string $name, string $ip, string $game = 'cs2'): Server
    {
        return Server::create([
            'name' => $name,
            'game' => $game,
            'ip' => $ip,
            'port' => 27015,
            'max_players' => 20,
            'enabled' => true,
        ]);
    }

    private function createStatus(
        Server $server,
        int $players,
        int $capacity,
        string $map = 'de_dust2',
        bool $online = true,
        $createdAt = null,
    ): void {
        ServerStatus::create([
            'server_id' => $server->id,
            'online' => $online,
            'players' => $players,
            'max_players' => $capacity,
            'map' => $map,
            'created_at' => $createdAt ?? now()->subMinute(),
        ]);
    }
}