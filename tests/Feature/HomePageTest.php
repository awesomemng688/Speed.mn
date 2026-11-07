<?php

namespace Tests\Feature;

use App\Models\Server;
use App\Models\ServerRating;
use App\Models\ServerStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_returns_successful_response(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertViewIs('home')
            ->assertSee('Сервер сонгох');
    }

    public function test_home_page_displays_correct_stats(): void
    {
        // Create servers with various statuses
        $cs2Online = $this->createServer('CS2 Online', '198.51.100.1', 'cs2');
        $this->createStatus($cs2Online, 10, 20);

        $cs2Offline = $this->createServer('CS2 Offline', '198.51.100.2', 'cs2');
        $this->createStatus($cs2Offline, 0, 20, 'de_dust2', false);

        $cs16Online = $this->createServer('CS16 Online', '203.0.113.1', 'cs16');
        $this->createStatus($cs16Online, 5, 16);

        $response = $this->get('/')->assertOk();

        $response->assertSee('<strong data-count="3">3</strong>', false)
            ->assertSee('<strong data-count="2">2</strong>', false)
            ->assertSee('<strong data-count="15">15</strong>', false)
            ->assertSee('2 сервер онлайн');
    }

    public function test_home_page_shows_featured_servers(): void
    {
        $cs2Servers = [];
        for ($i = 1; $i <= 4; $i++) {
            $cs2Servers[] = $this->createServer("CS2 Server {$i}", "198.51.100.{$i}", 'cs2');
        }

        $cs16Servers = [];
        for ($i = 1; $i <= 3; $i++) {
            $cs16Servers[] = $this->createServer("CS16 Server {$i}", "203.0.113.{$i}", 'cs16');
        }

        // Only some are online
        $this->createStatus($cs2Servers[0], 5, 20);
        $this->createStatus($cs2Servers[2], 8, 20);
        $this->createStatus($cs16Servers[0], 3, 16);

        $response = $this->get('/')->assertOk();

        // Should show max 3 CS2 and 3 CS16 servers (6 total)
        $this->assertSame(6, substr_count($response->getContent(), '<article class="server-card"'));
    }

    public function test_home_page_caching_works(): void
    {
        $server = $this->createServer('Cached Server', '198.51.100.50');
        $this->createStatus($server, 5, 20);

        // First request
        $this->get('/')->assertOk();

        // Second request should use cache
        $this->get('/')->assertOk();

        // Verify cache key exists
        $this->assertNotNull(cache('home-stats'));
    }

    public function test_home_page_weekly_best_server(): void
    {
        $server = $this->createServer('Best Server', '198.51.100.60');
        $this->createStatus($server, 10, 20);

        $user = User::factory()->create(['steam_id' => '76561198000000001']);

        // Add ratings
        ServerRating::create([
            'server_id' => $server->id,
            'user_id' => $user->id,
            'rating' => 5,
            'comment' => 'Great server',
        ]);

        $response = $this->get('/')->assertOk();
        $response->assertSee('7 ХОНОГИЙН ШИЛДЭГ СЕРВЕР');
    }

    public function test_home_page_hero_section_structure(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('МОНГОЛЫН CS СҮЛЖЭЭ')
            ->assertSee('Сервер сонгох')
            ->assertSee('Steam-ээр нэвтрэх');
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