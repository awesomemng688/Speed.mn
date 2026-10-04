<?php

namespace Tests\Feature;

use App\Models\Server;
use App\Models\ServerStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DiscordPlayersReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_sends_fresh_online_players_from_both_games_without_mentions(): void
    {
        config(['services.discord.players_webhook' => 'https://discord.com/api/webhooks/players-test']);
        Cache::forget('speedmn.discord.players.message_ids');
        Http::fake(fn (Request $request) => Http::response(['id' => 'report-message-1'], 200));

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
            ->expectsOutput('Updated Discord player report for 2 online server(s).')
            ->assertExitCode(0);

        Http::assertSentCount(1);
        $firstRequest = Http::recorded()->first()[0];
        $payload = $firstRequest->data();
        $description = $payload['embeds'][0]['description'];

        $this->assertSame('POST', $firstRequest->method());
        $this->assertStringContainsString('wait=true', $firstRequest->url());
        $this->assertSame('Speed.mn · Онлайн тоглогчид', $payload['embeds'][0]['title']);
        $this->assertStringContainsString('CS2 report server', $description);
        $this->assertStringContainsString('CS 1.6 report server', $description);
        $this->assertStringContainsString('### CS2', $description);
        $this->assertStringContainsString('### CS 1.6', $description);
        $this->assertStringContainsString('`de_dust2`', $description);
        $this->assertStringContainsString('1/32', $description);
        $this->assertStringContainsString('Player One', $description);
        $this->assertStringContainsString('01:01:01', $description);
        $this->assertStringNotContainsString('Offline server', $description);
        $this->assertStringNotContainsString('Stale server', $description);
        $this->assertStringNotContainsString('Disabled server', $description);
        $this->assertSame(['parse' => []], $payload['allowed_mentions']);

        $this->artisan('speedmn:discord-players')->assertExitCode(0);
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request) => $request->method() === 'PATCH'
            && str_ends_with($request->url(), '/messages/report-message-1'));
        $this->assertSame(['report-message-1'], Cache::get('speedmn.discord.players.message_ids'));
    }

    public function test_report_skips_safely_without_a_player_webhook(): void
    {
        config(['services.discord.players_webhook' => null]);
        Cache::forget('speedmn.discord.players.message_ids');
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