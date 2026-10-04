<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admins_can_open_user_management(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get(route('admin.users.index'))
            ->assertForbidden();
    }

    public function test_admin_can_grant_and_revoke_another_users_access(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAsAdmin($admin)
            ->patch(route('admin.users.toggle', $user))
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('status');

        $this->assertTrue($user->fresh()->is_admin);

        $this->actingAsAdmin($admin)
            ->patch(route('admin.users.toggle', $user))
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('status');

        $this->assertFalse($user->fresh()->is_admin);
    }

    public function test_admin_cannot_remove_their_own_access(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAsAdmin($admin)
            ->from(route('admin.users.index'))
            ->patch(route('admin.users.toggle', $admin))
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('error');

        $this->assertTrue($admin->fresh()->is_admin);
    }
}