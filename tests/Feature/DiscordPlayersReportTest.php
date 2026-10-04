<?php

namespace Tests\Feature;

use App\Models\Server;
use App\Models\ServerStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DiscordPlayersReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_sends_fresh_online_players_from_both_games_without_mentions(): void
    {
        config(['services.discord.players_webhook' => 'https://discord.com/api/webhooks/players-test']);
        Http::fake();

        $cs2 = $this->createServer('CS2 report server', 'cs2');
        $this->createStatus($cs2, true, now()->subMinute(), [
            ['name' => 'Player One', 'score' => 12, 'duration' => 3661],
        ]);
        $cs16 = $this->createServer('CS 1.6 report server', 'cs16');
        $this->createStatus($cs16, true, now()->subMinute(), []);

        $offline = $this->createServer('Offline server', 'cs2');
        $this->createStatus($offline, false, now()->subMinute());
        $stale = $this->createServer('Stale server', 'cs2');
        $this->createStatus($stale, true, now()->subMinutes(4));
        $disabled = $this->createServer('Disabled server', 'cs16', false);
        $this->createStatus($disabled, true, now()->subMinute());

        $this->artisan('speedmn:discord-players')
            ->expectsOutput('Sent player reports for 2 online server(s).')
            ->assertExitCode(0);

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => str_contains($request['content'], 'CS2 report server')
            && str_contains($request['content'], 'CS 1.6 report server')
            && str_contains($request['content'], 'Player One')
            && str_contains($request['content'], '01:01:01')
            && ! str_contains($request['content'], 'Offline server')
            && ! str_contains($request['content'], 'Stale server')
            && ! str_contains($request['content'], 'Disabled server')
            && $request['allowed_mentions'] === ['parse' => []]);
    }

    public function test_report_skips_safely_without_a_player_webhook(): void
    {
        config(['services.discord.players_webhook' => null]);
        Http::fake();

        $this->artisan('speedmn:discord-players')
            ->expectsOutput('DISCORD_SERVER_PLAYERS_WEBHOOK is not configured; player report skipped.')
            ->assertExitCode(0);

        Http::assertNothingSent();
    }

    private function createServer(string $name, string $game, bool $enabled = true): Server
    {
        static $nextPort = 27015;

        return Server::create([
            'name' => $name,
            'game' => $game,
            'ip' => '198.51.100.'.(++$nextPort - 27015),
            'port' => $nextPort,
            'max_players' => 32,
            'enabled' => $enabled,
        ]);
    }

    private function createStatus(Server $server, bool $online, $createdAt, array $players = []): void
    {
        ServerStatus::create([
            'server_id' => $server->id,
            'online' => $online,
            'players' => count($players),
            'max_players' => 32,
            'map' => 'de_dust2',
            'player_list' => $players,
            'created_at' => $createdAt,
        ]);
    }
}