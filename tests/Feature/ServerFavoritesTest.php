<?php

namespace Tests\Feature;

use App\Models\Server;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServerFavoritesTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_favorite_and_unfavorite_a_server(): void
    {
        $user = User::factory()->create();
        $server = $this->createServer();

        $this->actingAs($user)
            ->post(route('servers.favorite', $server))
            ->assertRedirect();

        $this->assertDatabaseHas('server_favorites', [
            'user_id' => $user->id,
            'server_id' => $server->id,
        ]);

        $this->get(route('servers.index'))
            ->assertOk()
            ->assertSee('aria-pressed="true"', false);
        $this->get(route('profile'))
            ->assertOk()
            ->assertSee('Дуртай серверүүд')
            ->assertSee('Favorite test server');

        $this->delete(route('servers.unfavorite', $server))
            ->assertRedirect();

        $this->assertDatabaseMissing('server_favorites', [
            'user_id' => $user->id,
            'server_id' => $server->id,
        ]);
    }

    public function test_guests_are_sent_to_steam_login_before_favoriting(): void
    {
        $this->post(route('servers.favorite', $this->createServer()))
            ->assertRedirect(route('steam.login'));
    }

    public function test_favorites_are_only_shown_as_saved_to_their_owner(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $server = $this->createServer();
        $owner->favoriteServers()->attach($server);

        $this->actingAs($otherUser)
            ->get(route('servers.index'))
            ->assertOk()
            ->assertSee('aria-pressed="false"', false)
            ->assertSee('☆ Дуртайд хадгалах');
    }

    private function createServer(): Server
    {
        return Server::create([
            'name' => 'Favorite test server',
            'game' => 'cs2',
            'ip' => '198.51.100.55',
            'port' => 27015,
            'max_players' => 20,
            'enabled' => true,
        ]);
    }
}