<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AdminTwoFactorTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_must_enroll_and_confirm_totp_before_entering_admin_tools(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('admin.servers.index'))
            ->assertRedirect(route('admin.two-factor.setup'));

        $setupResponse = $this->get(route('admin.two-factor.setup'))
            ->assertOk()
            ->assertViewIs('admin.two-factor.setup');
        $secret = session('admin_totp_setup_secret');
        $google2fa = app(Google2FA::class);

        $recoveryResponse = $this->post(route('admin.two-factor.confirm'), [
            'code' => $google2fa->getCurrentOtp($secret),
        ])->assertOk()->assertViewIs('admin.two-factor.recovery');

        $admin->refresh();
        $this->assertNotNull($admin->admin_totp_confirmed_at);
        $this->assertCount(8, $admin->admin_totp_recovery_codes);
        $this->assertSame(8, count($recoveryResponse->viewData('recoveryCodes')));
        $this->assertDatabaseHas('admin_audit_logs', [
            'actor_user_id' => $admin->id,
            'event' => 'admin.2fa_enabled',
        ]);

        $this->get(route('admin.servers.index'))->assertOk();
    }

    public function test_admin_can_use_a_recovery_code_once_for_challenge(): void
    {
        $admin = $this->createEnabledAdmin();
        $recoveryCode = 'FIRST-ONE-TIME-CODE';
        $admin->forceFill(['admin_totp_recovery_codes' => [Hash::make('FIRSTONETIMECODE')]])->save();
        $this->flushSession();
        $this->actingAs($admin);

        $this->get(route('admin.servers.index'))->assertRedirect(route('admin.two-factor.challenge'));
        $this->post(route('admin.two-factor.verify'), ['code' => $recoveryCode])
            ->assertRedirect(route('admin.servers.index'));

        $this->assertSame([], $admin->fresh()->admin_totp_recovery_codes);
        $this->assertDatabaseHas('admin_audit_logs', [
            'actor_user_id' => $admin->id,
            'event' => 'admin.2fa_recovery_used',
        ]);
    }

    public function test_totp_challenge_rate_limits_invalid_codes(): void
    {
        $admin = $this->createEnabledAdmin();
        $this->actingAs($admin)->get(route('admin.servers.index'))
            ->assertRedirect(route('admin.two-factor.challenge'));

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('admin.two-factor.verify'), ['code' => 'not-a-code'])
                ->assertSessionHasErrors('code');
        }

        $this->post(route('admin.two-factor.verify'), ['code' => 'not-a-code'])
            ->assertSessionHasErrors(['code' => 'Too many attempts. Wait one minute and retry.']);
    }

    public function test_admin_can_disable_totp_with_a_valid_code(): void
    {
        $admin = $this->createEnabledAdmin();
        $google2fa = app(Google2FA::class);

        $this->withSession(['admin_totp_verified_at' => now()->timestamp])
            ->actingAs($admin)
            ->delete(route('admin.two-factor.disable'), [
                'code' => $google2fa->getCurrentOtp($admin->admin_totp_secret),
            ])
            ->assertRedirect(route('admin.two-factor.setup'))
            ->assertSessionHas('status');

        $admin->refresh();
        $this->assertNull($admin->admin_totp_confirmed_at);
        $this->assertNull($admin->admin_totp_secret);
        $this->assertDatabaseHas('admin_audit_logs', [
            'actor_user_id' => $admin->id,
            'event' => 'admin.2fa_disabled',
        ]);
    }

    private function createEnabledAdmin(): User
    {
        $google2fa = app(Google2FA::class);

        return User::factory()->create([
            'is_admin' => true,
            'admin_totp_secret' => $google2fa->generateSecretKey(),
            'admin_totp_confirmed_at' => now(),
            'admin_totp_recovery_codes' => [],
        ]);
    }
}