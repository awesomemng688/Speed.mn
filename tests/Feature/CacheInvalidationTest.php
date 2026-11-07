<?php

namespace Tests\Feature;

use App\Models\Server;
use App\Models\ServerStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CacheInvalidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_stats_cache_is_invalidated_on_server_status_created(): void
    {
        // Create a server and status
        $server = Server::create([
            'name' => 'Test Server',
            'game' => 'cs2',
            'ip' => '198.51.100.1',
            'port' => 27015,
            'max_players' => 20,
            'enabled' => true,
        ]);

        $this->createStatus($server, 5, 20);

        // Prime the cache
        $this->get('/')->assertOk();
        $this->assertNotNull(Cache::get('home-stats'));

        // Create a new status - this should invalidate the cache via Observer
        $this->createStatus($server, 8, 20);

        // Cache should be forgotten (returns null)
        $this->assertNull(Cache::get('home-stats'));

        // Next request should repopulate cache
        $this->get('/')->assertOk();
        $this->assertNotNull(Cache::get('home-stats'));
    }

    public function test_multiple_status_creations_only_invalidate_once(): void
    {
        $server = Server::create([
            'name' => 'Test Server',
            'game' => 'cs2',
            'ip' => '198.51.100.2',
            'port' => 27015,
            'max_players' => 20,
            'enabled' => true,
        ]);

        $this->createStatus($server, 5, 20);
        $this->get('/')->assertOk();
        $this->assertNotNull(Cache::get('home-stats'));

        // Create multiple statuses
        $this->createStatus($server, 8, 20);
        $this->createStatus($server, 10, 20);
        $this->createStatus($server, 12, 20);

        // Cache should still be invalidated (null)
        $this->assertNull(Cache::get('home-stats'));
    }

    public function test_different_servers_invalidate_shared_cache(): void
    {
        $server1 = Server::create([
            'name' => 'Server 1',
            'game' => 'cs2',
            'ip' => '198.51.100.10',
            'port' => 27015,
            'max_players' => 20,
            'enabled' => true,
        ]);

        $server2 = Server::create([
            'name' => 'Server 2',
            'game' => 'cs16',
            'ip' => '203.0.113.10',
            'port' => 27015,
            'max_players' => 16,
            'enabled' => true,
        ]);

        $this->createStatus($server1, 5, 20);
        $this->createStatus($server2, 3, 16);

        $this->get('/')->assertOk();
        $this->assertNotNull(Cache::get('home-stats'));

        // New status on server1 should invalidate
        $this->createStatus($server1, 8, 20);
        $this->assertNull(Cache::get('home-stats'));

        // Repopulate
        $this->get('/')->assertOk();
        $this->assertNotNull(Cache::get('home-stats'));

        // New status on server2 should also invalidate
        $this->createStatus($server2, 5, 16);
        $this->assertNull(Cache::get('home-stats'));
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