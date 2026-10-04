<?php

namespace Tests\Feature;

use App\Models\Server;
use App\Models\ServerStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServerDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_filters_are_applied_before_pagination(): void
    {
        for ($index = 1; $index <= 13; $index++) {
            $this->createServer("Other server {$index}", '192.0.2.'.($index + 1));
        }

        $fullServer = $this->createServer('Full Mirage', '198.51.100.1');
        $this->createStatus($fullServer, 20, 20, 'de_mirage');

        $availableServer = $this->createServer('Available Mirage', '198.51.100.2');
        $this->createStatus($availableServer, 8, 20, 'de_mirage');

        $this->get('/servers?q=Full&map=mirage&players=full')
            ->assertOk()
            ->assertSee('Full Mirage')
            ->assertDontSee('Available Mirage');
    }

    public function test_online_and_offline_filters_only_include_fresh_statuses(): void
    {
        $online = $this->createServer('Online server', '198.51.100.30');
        $this->createStatus($online, 4, 20);

        $offline = $this->createServer('Offline server', '198.51.100.31');
        $this->createStatus($offline, 0, 20, 'de_dust2', false);

        $stale = $this->createServer('Stale server', '198.51.100.32');
        $this->createStatus($stale, 3, 20, 'de_dust2', true, now()->subMinutes(5));

        $this->get('/servers?status=online')
            ->assertOk()
            ->assertSee('Online server')
            ->assertDontSee('Offline server')
            ->assertDontSee('Stale server');

        $this->get('/servers?status=offline')
            ->assertOk()
            ->assertSee('Offline server')
            ->assertDontSee('Online server')
            ->assertDontSee('Stale server');
    }

    public function test_recommended_order_places_full_servers_first(): void
    {
        $availableServer = $this->createServer('Available server', '198.51.100.3');
        $this->createStatus($availableServer, 4, 20);

        $fullServer = $this->createServer('Full server', '198.51.100.4');
        $this->createStatus($fullServer, 20, 20);

        $offlineServer = $this->createServer('Offline server', '198.51.100.5');
        $this->createStatus($offlineServer, 20, 20);
        ServerStatus::create([
            'server_id' => $offlineServer->id,
            'online' => false,
            'players' => 0,
            'max_players' => 20,
            'created_at' => now(),
        ]);

        $this->get('/servers')
            ->assertOk()
            ->assertSeeInOrder(['Full server', 'Available server', 'Offline server']);
    }

    public function test_home_aggregates_every_server_but_renders_only_six_featured_cards(): void
    {
        $cs2Servers = [];
        for ($index = 1; $index <= 5; $index++) {
            $cs2Servers[] = $this->createServer("CS2 server {$index}", "198.51.100.{$index}");
        }

        $cs16Servers = [];
        for ($index = 1; $index <= 4; $index++) {
            $cs16Servers[] = $this->createServer("CS16 server {$index}", "203.0.113.{$index}", 'cs16');
        }

        $this->createStatus($cs2Servers[0], 5, 20);
        $this->createStatus($cs2Servers[1], 0, 20, 'de_dust2', false);
        $this->createStatus($cs2Servers[2], 10, 20, 'de_dust2', true, now()->subMinutes(5));
        $this->createStatus($cs2Servers[4], 3, 20);
        $this->createStatus($cs16Servers[0], 2, 16);

        $response = $this->get('/')->assertOk();

        $response->assertSee('<strong>9</strong>', false)
            ->assertSee('3 сервер онлайн')
            ->assertSee('<strong>10</strong>', false)
            ->assertSee('1 серверийн мэдээлэл хуучирсан, 4 серверийн төлөв ирээгүй');
        $this->assertSame(6, substr_count($response->getContent(), '<article class="server-card"'));
    }

    public function test_unknown_status_and_empty_search_have_distinct_messages(): void
    {
        $server = $this->createServer('No status server', '198.51.100.20');

        $this->get(route('servers.show', $server))
            ->assertOk()
            ->assertSee('МЭДЭЭЛЭЛ АЛГА');

        $this->get('/servers?q=not-found')
            ->assertOk()
            ->assertSee('Шүүлтэд тохирох сервер олдсонгүй.');
    }

    public function test_server_detail_shows_seven_and_thirty_day_statistics(): void
    {
        $server = $this->createServer('History server', '198.51.100.45');
        $this->createStatus($server, 10, 20, 'de_dust2', true, now()->subDays(2));
        $this->createStatus($server, 0, 20, 'de_dust2', false, now()->subDays(2)->subMinutes(30));
        $this->createStatus($server, 4, 20, 'de_mirage', true, now()->subDays(12));
        $this->createStatus($server, 20, 20, 'de_inferno', true, now()->subDays(40));

        $this->get(route('servers.show', ['server' => $server, 'period' => '7d']))
            ->assertOk()
            ->assertSee('50.00%')
            ->assertSee('7 хоног')
            ->assertSee('10.0');

        $this->get(route('servers.show', ['server' => $server, 'period' => '30d']))
            ->assertOk()
            ->assertSee('66.67%')
            ->assertSee('7.0')
            ->assertSee('10');
    }

    public function test_freshness_uses_a_single_three_minute_boundary(): void
    {
        $referenceTime = now()->startOfSecond();
        $atBoundary = new ServerStatus(['created_at' => $referenceTime->copy()->subMinutes(3)]);
        $justStale = new ServerStatus(['created_at' => $referenceTime->copy()->subMinutes(3)->subSecond()]);

        $this->assertTrue($atBoundary->isFresh($referenceTime));
        $this->assertFalse($justStale->isFresh($referenceTime));
        $this->assertSame('unknown', ServerStatus::stateOf(null, $referenceTime));
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
    ): void
    {
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