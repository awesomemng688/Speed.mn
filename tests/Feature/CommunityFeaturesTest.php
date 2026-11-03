<?php

namespace Tests\Feature;

use App\Models\Post;
use App\Models\Server;
use App\Models\ServerRating;
use App\Models\TournamentRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunityFeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_can_register_for_a_published_tournament_once(): void
    {
        $user = User::factory()->create();
        $post = Post::create([
            'type' => 'tournament',
            'title' => 'Cup',
            'slug' => 'cup',
            'body' => 'Details',
            'is_published' => true,
            'published_at' => now(),
            'event_at' => now()->addDay(),
        ]);

        $this->actingAs($user)->post(route('tournaments.register', $post), [
            'participant_name' => 'Player',
            'team_name' => 'Team',
        ])->assertSessionHasNoErrors();

        $this->actingAs($user)->post(route('tournaments.register', $post), [
            'participant_name' => 'Player',
            'team_name' => 'Team',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, TournamentRegistration::count());
        $this->get(route('news.show', $post))->assertOk()->assertSee('Team');
    }

    public function test_player_can_rate_server_and_update_their_rating(): void
    {
        $user = User::factory()->create();
        $server = Server::query()->create([
            'name' => 'Community',
            'game' => 'cs2',
            'ip' => '127.0.0.1',
            'port' => 27015,
            'max_players' => 20,
            'enabled' => true,
        ]);

        $this->actingAs($user)->post(route('servers.rating', $server), [
            'rating' => 5,
            'comment' => 'Good server',
        ])->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('servers.rating', $server), [
            'rating' => 4,
            'comment' => 'Still good',
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, ServerRating::count());
        $this->assertSame(4, ServerRating::firstOrFail()->rating);
        $this->get(route('servers.show', $server))->assertOk()->assertSee('4.0 / 5');
    }

    public function test_guests_cannot_submit_ratings_or_tournament_registrations(): void
    {
        $server = Server::query()->create([
            'name' => 'Community',
            'game' => 'cs2',
            'ip' => '127.0.0.1',
            'port' => 27015,
            'max_players' => 20,
            'enabled' => true,
        ]);
        $post = Post::create([
            'type' => 'tournament',
            'title' => 'Cup',
            'slug' => 'cup',
            'body' => 'Details',
            'is_published' => true,
            'published_at' => now(),
            'event_at' => now()->addDay(),
        ]);

        $this->post(route('servers.rating', $server), ['rating' => 5])->assertRedirect();
        $this->post(route('tournaments.register', $post), ['participant_name' => 'Player'])->assertRedirect();
        $this->assertSame(0, ServerRating::count());
        $this->assertSame(0, TournamentRegistration::count());
    }
}
