<?php

namespace Tests\Feature;

use App\Jobs\PollServer;
use App\Models\Server;
use App\Models\ServerStatus;
use App\Models\User;
use App\Services\ServerQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ServerPollingTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_poll_updates_last_success_and_clears_query_error(): void
    {
        $server = $this->createServer();
        $server->forceFill(['last_query_error' => 'Previous timeout'])->save();
        $query = \Mockery::mock(ServerQueryService::class);
        $query->shouldReceive('query')->once()
            ->with(\Mockery::on(fn (Server $candidate) => $candidate->is($server)))
            ->andReturn($this->successfulResult());

        (new PollServer($server->id))->handle($query);

        $server->refresh();
        $this->assertNotNull($server->last_polled_at);
        $this->assertNotNull($server->last_successful_poll_at);
        $this->assertNull($server->last_query_error);
        $this->assertTrue($server->latestStatus->online);

        $apiServer = $this->getJson('/api/servers?per_page=100')->assertOk()->json('data.0');
        $this->assertSame('online', $apiServer['status_state']);
        $this->assertNotNull($apiServer['last_successful_poll_at']);
    }

    public function test_failed_query_records_reason_without_erasing_last_success(): void
    {
        $server = $this->createServer();
        $lastSuccess = now()->subMinutes(4);
        $server->forceFill(['last_successful_poll_at' => $lastSuccess])->save();
        $query = \Mockery::mock(ServerQueryService::class);
        $query->shouldReceive('query')->once()
            ->with(\Mockery::on(fn (Server $candidate) => $candidate->is($server)))
            ->andReturn([
            'online' => false,
            'players' => 0,
            'max_players' => null,
            'bots' => null,
            'vac' => null,
            'map' => null,
            'response_time' => 2000,
            'player_list' => [],
            'version' => null,
            'query_succeeded' => false,
            'query_error' => 'A2S query timed out',
            ]);

        (new PollServer($server->id))->handle($query);

        $server->refresh();
        $this->assertNotNull($server->last_polled_at);
        $this->assertSame($lastSuccess->timestamp, $server->last_successful_poll_at->timestamp);
        $this->assertSame('A2S query timed out', $server->last_query_error);
        $this->assertFalse($server->latestStatus->online);
    }

    public function test_poll_command_records_scheduler_heartbeat_and_queues_enabled_servers(): void
    {
        $server = $this->createServer();
        Queue::fake();

        Artisan::call('speedmn:poll');

        $this->assertNotNull(Cache::get('speedmn.poll.last_dispatched_at'));
        Queue::assertPushed(PollServer::class, fn (PollServer $job) => $job->serverId === $server->id);
    }

    public function test_poll_command_still_queues_jobs_when_heartbeat_cache_fails(): void
    {
        $server = $this->createServer();
        Queue::fake();
        Cache::shouldReceive('put')->once()->andThrow(new \RuntimeException('cache unavailable'));

        Artisan::call('speedmn:poll');

        Queue::assertPushed(PollServer::class, fn (PollServer $job) => $job->serverId === $server->id);
    }

    public function test_successful_poll_is_not_failed_when_worker_heartbeat_cache_fails(): void
    {
        $server = $this->createServer();
        $query = \Mockery::mock(ServerQueryService::class);
        $query->shouldReceive('query')->once()
            ->with(\Mockery::on(fn (Server $candidate) => $candidate->is($server)))
            ->andReturn($this->successfulResult());
        Cache::shouldReceive('put')->once()->andThrow(new \RuntimeException('cache unavailable'));

        (new PollServer($server->id))->handle($query);

        $server->refresh();
        $this->assertNotNull($server->last_successful_poll_at);
        $this->assertTrue($server->latestStatus->online);
    }

    public function test_admin_server_page_shows_queue_and_query_diagnostics(): void
    {
        $server = $this->createServer();
        $server->forceFill([
            'last_polled_at' => now()->subMinute(),
            'last_successful_poll_at' => now()->subMinutes(5),
            'last_query_error' => 'A2S query timed out',
        ])->save();
        Cache::put('speedmn.poll.last_dispatched_at', now()->toIso8601String());
        Cache::put('speedmn.poll.last_completed_at', now()->toIso8601String());
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('admin.servers.index'))
            ->assertOk()
            ->assertSee('Queue worker:')
            ->assertSee('A2S query timed out')
            ->assertSee('Last success');
    }

    public function test_admin_server_page_survives_unavailable_heartbeat_cache(): void
    {
        $this->createServer();
        $admin = User::factory()->create(['is_admin' => true]);
        Cache::shouldReceive('get')->times(3)->andThrow(new \RuntimeException('cache unavailable'));

        $this->actingAs($admin)
            ->get(route('admin.servers.index'))
            ->assertOk()
            ->assertSee('no recent jobs');
    }

    public function test_permanently_failed_job_records_the_reason_for_admin(): void
    {
        $server = $this->createServer();

        (new PollServer($server->id))->failed(new \RuntimeException('Database queue connection lost'));

        $this->assertSame(
            'Polling job failed: Database queue connection lost',
            $server->fresh()->last_query_error,
        );
    }

    private function createServer(): Server
    {
        return Server::create([
            'name' => 'Polling test server',
            'game' => 'cs2',
            'ip' => '192.0.2.1',
            'port' => 27015,
            'max_players' => 20,
            'enabled' => true,
        ]);
    }

    private function successfulResult(): array
    {
        return [
            'online' => true,
            'players' => 4,
            'max_players' => 20,
            'bots' => 0,
            'vac' => true,
            'map' => 'de_dust2',
            'response_time' => 42,
            'player_list' => [],
            'version' => 'test',
            'query_succeeded' => true,
            'query_error' => null,
        ];
    }
}