<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function actingAsAdmin(User $admin): static
    {
        if (! $admin->admin_totp_confirmed_at) {
            $admin->forceFill([
                'admin_totp_secret' => 'test-admin-totp-secret',
                'admin_totp_confirmed_at' => now(),
                'admin_totp_recovery_codes' => [],
            ])->save();
        }

        return $this->withSession(['admin_totp_verified_at' => now()->timestamp])
            ->actingAs($admin);
    }
}
