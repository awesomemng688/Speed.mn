<?php

namespace Tests\Feature;

use App\Models\Server;
use App\Models\ServerStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServerStatusRetentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_prune_removes_only_statuses_older_than_the_retention_period(): void
    {
        $server = Server::create([
            'name' => 'Retention test server',
            'game' => 'cs2',
            'ip' => '198.51.100.80',
            'port' => 27015,
            'enabled' => true,
        ]);
        $expired = ServerStatus::create([
            'server_id' => $server->id,
            'online' => true,
            'players' => 3,
            'created_at' => now()->subDays(91),
        ]);
        $recent = ServerStatus::create([
            'server_id' => $server->id,
            'online' => false,
            'players' => 0,
            'created_at' => now()->subDays(89),
        ]);

        $this->artisan('speedmn:status-prune')
            ->expectsOutput('Removed 1 server status row(s) older than 90 days.')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('server_statuses', ['id' => $expired->id]);
        $this->assertDatabaseHas('server_statuses', ['id' => $recent->id]);
    }
}