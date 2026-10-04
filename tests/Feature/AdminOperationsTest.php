<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Jobs\PollServer;
use App\Models\Server;
use App\Models\ServerStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_filter_servers_by_status_and_query_error(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $failedServer = $this->createServer('Failed CS2 server');
        $failedServer->forceFill([
            'last_polled_at' => now(),
            'last_query_error' => 'A2S timed out',
        ])->save();
        $this->createStatus($failedServer, false, now());

        $onlineServer = $this->createServer('Online CS2 server');
        $onlineServer->forceFill(['last_polled_at' => now()])->save();
        $this->createStatus($onlineServer, true, now());

        $this->actingAsAdmin($admin)
            ->get(route('admin.servers.index', ['status' => 'offline', 'error' => 'yes']))
            ->assertOk()
            ->assertSee('Failed CS2 server')
            ->assertDontSee('Online CS2 server');
    }

    public function test_admin_can_queue_a_poll_for_an_enabled_server(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $server = $this->createServer('Poll now server');
        Queue::fake();

        $this->actingAsAdmin($admin)
            ->post(route('admin.servers.poll', $server))
            ->assertRedirect()
            ->assertSessionHas('status');

        Queue::assertPushed(PollServer::class, fn (PollServer $job) => $job->serverId === $server->id);
        $this->assertDatabaseHas('admin_audit_logs', [
            'actor_user_id' => $admin->id,
            'event' => 'server.poll_dispatched',
            'subject_id' => $server->id,
        ]);
    }

    public function test_admin_server_sort_orders_by_poll_freshness(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $neverPolled = $this->createServer('Never polled server');
        $oldPoll = $this->createServer('Old poll server');
        $oldPoll->forceFill(['last_polled_at' => now()->subHour()])->save();
        $recentPoll = $this->createServer('Recent poll server');
        $recentPoll->forceFill(['last_polled_at' => now()])->save();

        $this->actingAsAdmin($admin)
            ->get(route('admin.servers.index', ['sort' => 'poll_newest']))
            ->assertOk()
            ->assertSeeInOrder(['Recent poll server', 'Old poll server', 'Never polled server']);

        $this->actingAsAdmin($admin)
            ->get(route('admin.servers.index', ['sort' => 'poll_oldest']))
            ->assertOk()
            ->assertSeeInOrder(['Never polled server', 'Old poll server', 'Recent poll server']);
    }

    public function test_monitor_alerts_only_on_state_changes_and_sends_recovery(): void
    {
        $server = $this->createServer('Stale monitor server');
        config(['services.discord.admin_webhook' => 'https://discord.com/api/webhooks/test']);
        Cache::forget('speedmn.monitor.alert_state');
        Http::fake();

        Artisan::call('speedmn:monitor');
        Artisan::call('speedmn:monitor');
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => str_contains($request['content'], 'monitoring alert'));

        $server->forceFill(['last_polled_at' => now()])->save();
        Artisan::call('speedmn:monitor');
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => str_contains($request['content'], 'monitoring recovered'));
    }

    public function test_monitor_test_option_sends_without_changing_alert_state(): void
    {
        config(['services.discord.admin_webhook' => 'https://discord.com/api/webhooks/test']);
        Cache::put('speedmn.monitor.alert_state', 'stale');
        Http::fake();

        Artisan::call('speedmn:monitor', ['--test' => true]);

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => str_contains($request['content'], 'Discord delivery test')
            && str_contains($request['content'], 'stale-server and failed-job'));
        $this->assertSame('stale', Cache::get('speedmn.monitor.alert_state'));
    }

    public function test_monitor_test_option_returns_failure_when_discord_rejects_delivery(): void
    {
        config(['services.discord.admin_webhook' => 'https://discord.com/api/webhooks/test']);
        Http::fake(['discord.com/*' => Http::response([], 500)]);

        $exitCode = Artisan::call('speedmn:monitor', ['--test' => true]);

        $this->assertSame(1, $exitCode);
    }

    public function test_monitor_alerts_for_recent_failed_jobs_and_sends_recovery(): void
    {
        config(['services.discord.admin_webhook' => 'https://discord.com/api/webhooks/test']);
        Cache::forget('speedmn.monitor.alert_state');
        Http::fake();
        $uuid = (string) Str::uuid();
        DB::table('failed_jobs')->insert($this->failedJob($uuid));

        Artisan::call('speedmn:monitor');
        Artisan::call('speedmn:monitor');

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => str_contains($request['content'], '1 queue job(s) failed in the last 5 minutes'));

        DB::table('failed_jobs')->where('uuid', $uuid)->update(['failed_at' => now()->subMinutes(6)]);
        Artisan::call('speedmn:monitor');

        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => str_contains($request['content'], 'monitoring recovered'));
    }

    public function test_audit_prune_removes_only_entries_past_configured_retention(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $expired = AdminAuditLog::create([
            'actor_user_id' => $admin->id,
            'event' => 'expired.event',
        ]);
        $expired->forceFill([
            'created_at' => now()->subDays(366),
            'updated_at' => now()->subDays(366),
        ])->save();
        $recent = AdminAuditLog::create([
            'actor_user_id' => $admin->id,
            'event' => 'recent.event',
        ]);

        config(['speedmn.audit_retention_days' => 365]);
        Artisan::call('speedmn:audit-prune');

        $this->assertDatabaseMissing('admin_audit_logs', ['id' => $expired->id]);
        $this->assertDatabaseHas('admin_audit_logs', ['id' => $recent->id]);
    }

    public function test_server_creation_writes_audit_event_without_field_values(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAsAdmin($admin)
            ->post(route('admin.servers.store'), [
                'name' => 'Audited server',
                'game' => 'cs2',
                'ip' => '192.0.2.45',
                'port' => 27015,
                'max_players' => 20,
                'query_type' => 'a2s',
                'enabled' => true,
            ])
            ->assertRedirect(route('admin.servers.index'));

        $audit = AdminAuditLog::where('event', 'server.created')->sole();
        $this->assertSame($admin->id, $audit->actor_user_id);
        $this->assertSame('Audited server', $audit->subject_label);
        $this->assertContains('ip', $audit->details['fields']);
        $this->assertStringNotContainsString('192.0.2.45', json_encode($audit->details));

        $server = Server::where('name', 'Audited server')->firstOrFail();
        $this->actingAsAdmin($admin)
            ->put(route('admin.servers.update', $server), [
                'name' => 'Renamed audited server',
                'game' => 'cs2',
                'ip' => '192.0.2.45',
                'port' => 27015,
                'max_players' => 20,
                'query_type' => 'a2s',
                'enabled' => true,
            ])
            ->assertRedirect(route('admin.servers.index'));

        $updateAudit = AdminAuditLog::where('event', 'server.updated')->sole();
        $this->assertSame(['name'], $updateAudit->details['changed_fields']);
        $this->assertStringNotContainsString('Renamed audited server', json_encode($updateAudit->details));
    }

    public function test_admin_role_grants_and_revocations_are_audited(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $target = User::factory()->create(['is_admin' => false]);

        $this->actingAsAdmin($admin)->patch(route('admin.users.toggle', $target))->assertRedirect();
        $this->assertDatabaseHas('admin_audit_logs', [
            'actor_user_id' => $admin->id,
            'event' => 'admin.access_granted',
            'subject_id' => $target->id,
        ]);

        $this->actingAsAdmin($admin)->patch(route('admin.users.toggle', $target))->assertRedirect();
        $this->assertDatabaseHas('admin_audit_logs', [
            'actor_user_id' => $admin->id,
            'event' => 'admin.access_revoked',
            'subject_id' => $target->id,
        ]);
    }

    public function test_failed_job_can_be_retried_and_is_audited(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $uuid = (string) Str::uuid();
        DB::table('failed_jobs')->insert($this->failedJob($uuid));

        $this->actingAsAdmin($admin)
            ->post(route('admin.failed-jobs.retry', $uuid))
            ->assertRedirect(route('admin.failed-jobs.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('failed_jobs', ['uuid' => $uuid]);
        $this->assertDatabaseCount('jobs', 1);
        $this->assertDatabaseHas('admin_audit_logs', [
            'actor_user_id' => $admin->id,
            'event' => 'queue.failed_job_retried',
            'subject_label' => 'Server status poll',
        ]);
    }

    public function test_failed_job_can_be_forgotten_and_is_audited(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $uuid = (string) Str::uuid();
        DB::table('failed_jobs')->insert($this->failedJob($uuid));

        $this->actingAsAdmin($admin)
            ->delete(route('admin.failed-jobs.destroy', $uuid))
            ->assertRedirect(route('admin.failed-jobs.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('failed_jobs', ['uuid' => $uuid]);
        $this->assertDatabaseHas('admin_audit_logs', [
            'actor_user_id' => $admin->id,
            'event' => 'queue.failed_job_forgotten',
            'subject_label' => 'Server status poll',
        ]);
    }

    public function test_non_poll_failed_jobs_cannot_be_retried_from_admin_panel(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $uuid = (string) Str::uuid();
        DB::table('failed_jobs')->insert($this->failedJob($uuid, 'App\\Jobs\\UnrelatedJob'));

        $this->actingAsAdmin($admin)
            ->from(route('admin.failed-jobs.index'))
            ->post(route('admin.failed-jobs.retry', $uuid))
            ->assertRedirect(route('admin.failed-jobs.index'))
            ->assertSessionHas('error', 'Only server-poll jobs can be retried here.');

        $this->assertDatabaseHas('failed_jobs', ['uuid' => $uuid]);
        $this->assertSame(0, AdminAuditLog::where('event', 'queue.failed_job_retried')->count());
    }

    public function test_audit_log_lists_actor_and_recorded_event(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        AdminAuditLog::create([
            'actor_user_id' => $admin->id,
            'event' => 'server.enabled',
            'subject_type' => 'Server',
            'subject_id' => 1,
            'subject_label' => 'Test server',
            'details' => ['enabled' => true],
            'ip_address' => '192.0.2.10',
        ]);

        $this->actingAsAdmin($admin)
            ->get(route('admin.audit.index', ['event' => 'server.enabled']))
            ->assertOk()
            ->assertSee($admin->name)
            ->assertSee('Test server')
            ->assertSee('server.enabled');
    }

    private function createServer(string $name): Server
    {
        return Server::create([
            'name' => $name,
            'game' => 'cs2',
            'ip' => '192.0.2.'.random_int(10, 200),
            'port' => random_int(27000, 27099),
            'max_players' => 20,
            'enabled' => true,
        ]);
    }

    private function createStatus(Server $server, bool $online, $createdAt): void
    {
        ServerStatus::create([
            'server_id' => $server->id,
            'online' => $online,
            'players' => $online ? 5 : 0,
            'max_players' => 20,
            'created_at' => $createdAt,
        ]);
    }

    private function failedJob(string $uuid, string $jobClass = PollServer::class): array
    {
        return [
            'uuid' => $uuid,
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode([
                'uuid' => $uuid,
                'displayName' => $jobClass === PollServer::class ? 'Server status poll' : 'Unrelated job',
                'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
                'data' => ['commandName' => $jobClass],
            ]),
            'exception' => "Example failure\nStack trace hidden in summary",
            'failed_at' => now(),
        ];
    }
}