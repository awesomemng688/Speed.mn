<?php

namespace Tests\Feature;

use App\Jobs\PollServer;
use App\Models\Server;
use App\Models\ServerStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PollServerJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_poll_server_job_is_dispatched_to_redis_queue(): void
    {
        Queue::fake();

        $server = Server::create([
            'name' => 'Test Server',
            'game' => 'cs2',
            'ip' => '198.51.100.1',
            'port' => 27015,
            'max_players' => 20,
            'enabled' => true,
        ]);

        PollServer::dispatch($server->id);

        Queue::assertPushed(PollServer::class, function ($job) use ($server) {
            return $job->serverId === $server->id && $job->connection === 'redis';
        });
    }

    public function test_poll_server_job_handles_missing_server(): void
    {
        // Dispatch job for non-existent server
        $job = new PollServer(999999);
        $job->handle(app(\App\Services\ServerQueryService::class));

        // Should not throw exception
        $this->assertTrue(true);
    }

    public function test_poll_server_job_handles_disabled_server(): void
    {
        $server = Server::create([
            'name' => 'Disabled Server',
            'game' => 'cs2',
            'ip' => '198.51.100.1',
            'port' => 27015,
            'max_players' => 20,
            'enabled' => false,
        ]);

        $job = new PollServer($server->id);
        $job->handle(app(\App\Services\ServerQueryService::class));

        // Should not create status for disabled server
        $this->assertDatabaseMissing('server_statuses', ['server_id' => $server->id]);
    }
}