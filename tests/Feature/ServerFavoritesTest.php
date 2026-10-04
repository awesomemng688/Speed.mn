<?php

namespace Tests\Feature;

use App\Models\Server;
use App\Models\ServerStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
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

    public function test_profile_can_sort_favorites_by_online_players(): void
    {
        $user = User::factory()->create();
        $quiet = $this->createServer('Quiet favorite', '198.51.100.56');
        $busy = $this->createServer('Busy favorite', '198.51.100.57');
        $user->favoriteServers()->attach([$quiet->id, $busy->id]);
        ServerStatus::create([
            'server_id' => $quiet->id,
            'online' => true,
            'players' => 2,
            'max_players' => 20,
            'created_at' => now(),
        ]);
        ServerStatus::create([
            'server_id' => $busy->id,
            'online' => true,
            'players' => 15,
            'max_players' => 20,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->get(route('profile', ['favorites_sort' => 'players']))
            ->assertOk();

        $this->assertSame(
            ['Busy favorite', 'Quiet favorite'],
            $response->viewData('favorites')->pluck('name')->all(),
        );
    }

    public function test_profile_notification_can_be_marked_read(): void
    {
        $user = User::factory()->create();
        $notificationId = (string) Str::uuid();
        DB::table('notifications')->insert([
            'id' => $notificationId,
            'type' => 'App\\Notifications\\ServerStatusChanged',
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'data' => json_encode([
                'server_id' => 10,
                'server_name' => 'Alert favorite',
                'address' => '198.51.100.60:27015',
                'status' => 'offline',
                'changed_at' => now()->toIso8601String(),
            ]),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('profile'))
            ->assertOk()
            ->assertSee('Alert favorite')
            ->assertSee('офлайн боллоо');

        $this->post(route('notifications.read', $notificationId))->assertRedirect();
        $this->assertDatabaseHas('notifications', [
            'id' => $notificationId,
            'notifiable_id' => $user->id,
            'read_at' => now(),
        ]);
    }

    private function createServer(string $name = 'Favorite test server', string $ip = '198.51.100.55'): Server
    {
        return Server::create([
            'name' => $name,
            'game' => 'cs2',
            'ip' => $ip,
            'port' => 27015,
            'max_players' => 20,
            'enabled' => true,
        ]);
    }
}