<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\Server;
use App\Models\ServerStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

        $this->actingAs($admin)
            ->get(route('admin.servers.index', ['status' => 'offline', 'error' => 'yes']))
            ->assertOk()
            ->assertSee('Failed CS2 server')
            ->assertDontSee('Online CS2 server');
    }

    public function test_server_creation_writes_audit_event_without_field_values(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
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
        $this->actingAs($admin)
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

        $this->actingAs($admin)->patch(route('admin.users.toggle', $target))->assertRedirect();
        $this->assertDatabaseHas('admin_audit_logs', [
            'actor_user_id' => $admin->id,
            'event' => 'admin.access_granted',
            'subject_id' => $target->id,
        ]);

        $this->actingAs($admin)->patch(route('admin.users.toggle', $target))->assertRedirect();
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

        $this->actingAs($admin)
            ->post(route('admin.failed-jobs.retry', $uuid))
            ->assertRedirect(route('admin.failed-jobs.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('failed_jobs', ['uuid' => $uuid]);
        $this->assertDatabaseCount('jobs', 1);
        $this->assertDatabaseHas('admin_audit_logs', [
            'actor_user_id' => $admin->id,
            'event' => 'queue.failed_job_retried',
            'subject_label' => 'Example queued job',
        ]);
    }

    public function test_failed_job_can_be_forgotten_and_is_audited(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $uuid = (string) Str::uuid();
        DB::table('failed_jobs')->insert($this->failedJob($uuid));

        $this->actingAs($admin)
            ->delete(route('admin.failed-jobs.destroy', $uuid))
            ->assertRedirect(route('admin.failed-jobs.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('failed_jobs', ['uuid' => $uuid]);
        $this->assertDatabaseHas('admin_audit_logs', [
            'actor_user_id' => $admin->id,
            'event' => 'queue.failed_job_forgotten',
            'subject_label' => 'Example queued job',
        ]);
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

        $this->actingAs($admin)
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

    private function failedJob(string $uuid): array
    {
        return [
            'uuid' => $uuid,
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode([
                'uuid' => $uuid,
                'displayName' => 'Example queued job',
                'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
                'data' => [],
            ]),
            'exception' => "Example failure\nStack trace hidden in summary",
            'failed_at' => now(),
        ];
    }
}