<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_steam_login_redirect(): void
    {
        $response = $this->get('/auth/steam');

        // Should redirect to Steam OAuth
        $response->assertRedirect();
        $this->assertStringContainsString('steamcommunity.com', $response->headers->get('Location'));
    }

    public function test_login_redirect(): void
    {
        $response = $this->get('/login');

        // Should redirect to Steam OAuth
        $response->assertRedirect();
        $this->assertStringContainsString('steamcommunity.com', $response->headers->get('Location'));
    }

    public function test_protected_routes_require_authentication(): void
    {
        $protectedRoutes = [
            'profile' => route('profile'),
            'skins.bridge' => route('skins.bridge'),
            'submissions.create' => route('submissions.create'),
        ];

        foreach ($protectedRoutes as $name => $url) {
            $this->get($url)
                ->assertRedirect()
                ->assertRedirectContains('/auth/steam');
        }
    }

    public function test_authenticated_user_can_access_profile(): void
    {
        $user = User::factory()->create([
            'steam_id' => '76561198000000001',
            'name' => 'TestUser',
        ]);

        $this->actingAs($user)
            ->get(route('profile'))
            ->assertOk()
            ->assertSee('TestUser');
    }

    public function test_authenticated_user_can_access_skins_bridge(): void
    {
        $user = User::factory()->create([
            'steam_id' => '76561198000000001',
            'name' => 'TestUser',
        ]);

        $this->actingAs($user)
            ->get(route('skins.bridge'))
            ->assertOk();
    }

    public function test_logout_works(): void
    {
        $user = User::factory()->create([
            'steam_id' => '76561198000000001',
        ]);

        $this->actingAs($user)
            ->get(route('steam.logout'))
            ->assertRedirect('/');

        $this->assertGuest();
    }

    public function test_steam_callback_requires_valid_state(): void
    {
        // Callback without valid state should fail gracefully
        $this->get('/auth/steam/callback')
            ->assertStatus(500);
    }

    public function test_favorite_requires_authentication(): void
    {
        $server = \App\Models\Server::create([
            'name' => 'Test Server',
            'game' => 'cs2',
            'ip' => '198.51.100.1',
            'port' => 27015,
            'max_players' => 20,
            'enabled' => true,
        ]);

        $this->post(route('servers.favorite', $server))
            ->assertRedirect()
            ->assertRedirectContains('/auth/steam');
    }

    public function test_authenticated_user_can_favorite_server(): void
    {
        $user = User::factory()->create([
            'steam_id' => '76561198000000001',
        ]);

        $server = \App\Models\Server::create([
            'name' => 'Test Server',
            'game' => 'cs2',
            'ip' => '198.51.100.1',
            'port' => 27015,
            'max_players' => 20,
            'enabled' => true,
        ]);

        $this->actingAs($user)
            ->post(route('servers.favorite', $server))
            ->assertRedirect();

        $this->assertDatabaseHas('server_user', [
            'user_id' => $user->id,
            'server_id' => $server->id,
        ]);
    }

    public function test_authenticated_user_can_unfavorite_server(): void
    {
        $user = User::factory()->create([
            'steam_id' => '76561198000000001',
        ]);

        $server = \App\Models\Server::create([
            'name' => 'Test Server',
            'game' => 'cs2',
            'ip' => '198.51.100.1',
            'port' => 27015,
            'max_players' => 20,
            'enabled' => true,
        ]);

        // First favorite
        $user->favoritedServers()->attach($server->id);

        $this->actingAs($user)
            ->delete(route('servers.unfavorite', $server))
            ->assertRedirect();

        $this->assertDatabaseMissing('server_user', [
            'user_id' => $user->id,
            'server_id' => $server->id,
        ]);
    }

    public function test_rating_requires_authentication(): void
    {
        $server = \App\Models\Server::create([
            'name' => 'Test Server',
            'game' => 'cs2',
            'ip' => '198.51.100.1',
            'port' => 27015,
            'max_players' => 20,
            'enabled' => true,
        ]);

        $this->post(route('servers.rating', $server), [
            'rating' => 5,
        ])->assertRedirect()
            ->assertRedirectContains('/auth/steam');
    }

    public function test_authenticated_user_can_rate_server(): void
    {
        $user = User::factory()->create([
            'steam_id' => '76561198000000001',
        ]);

        $server = \App\Models\Server::create([
            'name' => 'Test Server',
            'game' => 'cs2',
            'ip' => '198.51.100.1',
            'port' => 27015,
            'max_players' => 20,
            'enabled' => true,
        ]);

        $this->actingAs($user)
            ->post(route('servers.rating', $server), [
                'rating' => 5,
                'comment' => 'Great server!',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('server_ratings', [
            'user_id' => $user->id,
            'server_id' => $server->id,
            'rating' => 5,
            'comment' => 'Great server!',
        ]);
    }

    public function test_admin_routes_require_admin(): void
    {
        $user = User::factory()->create([
            'steam_id' => '76561198000000001',
        ]);

        $adminRoutes = [
            route('admin.dashboard'),
            route('admin.servers.index'),
            route('admin.settings.edit'),
        ];

        // Non-admin should get 403
        foreach ($adminRoutes as $url) {
            $this->actingAs($user)
                ->get($url)
                ->assertForbidden();
        }

        // Admin should get access
        $admin = User::factory()->create([
            'steam_id' => '76561198000000002',
            'is_admin' => true,
        ]);

        foreach ($adminRoutes as $url) {
            $this->actingAsAdmin($admin)
                ->get($url)
                ->assertOk();
        }
    }
}