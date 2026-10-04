<?php

namespace Tests\Feature;

use App\Models\Server;
use App\Models\ServerStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_server_detail_api_includes_recent_monitoring_history(): void
    {
        $server = Server::create([
            'name' => 'Test server',
            'game' => 'cs2',
            'ip' => '127.0.0.1',
            'port' => 27015,
            'max_players' => 16,
            'enabled' => true,
        ]);

        $server->statuses()->createMany([
            [
                'server_id' => $server->id,
                'online' => true,
                'players' => 4,
                'max_players' => 16,
                'response_time' => 42,
                'created_at' => now()->subHour(),
            ],
            [
                'server_id' => $server->id,
                'online' => false,
                'players' => 0,
                'max_players' => 16,
                'response_time' => null,
                'created_at' => now()->subMinutes(30),
            ],
        ]);

        $this->getJson("/api/servers/{$server->id}")
            ->assertOk()
            ->assertJsonPath('data.monitoring.checks', 2)
            ->assertJsonPath('data.monitoring.uptime_percentage', 50)
            ->assertJsonPath('data.monitoring.peak_players', 4)
            ->assertJsonCount(2, 'data.monitoring.history')
            ->assertJsonCount(1, 'data.monitoring.offline_history');
    }
}
