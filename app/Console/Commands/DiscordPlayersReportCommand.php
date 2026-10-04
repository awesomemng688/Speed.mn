<?php

namespace App\Console\Commands;

use App\Models\Server;
use App\Models\ServerStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class DiscordPlayersReportCommand extends Command
{
    protected $signature = 'speedmn:discord-players';

    protected $description = 'Send online CS server players to the configured Discord webhook';

    public function handle(): int
    {
        $webhook = config('services.discord.players_webhook');
        if (! $webhook) {
            $this->comment('DISCORD_SERVER_PLAYERS_WEBHOOK is not configured; player report skipped.');

            return self::SUCCESS;
        }

        $onlineServers = Server::query()
            ->where('enabled', true)
            ->with('latestStatus')
            ->get()
            ->filter(fn (Server $server) => ServerStatus::stateOf($server->latestStatus) === 'online')
            ->sortBy(fn (Server $server) => [
                $server->game === 'cs2' ? 0 : 1,
                -((int) $server->latestStatus->players),
                $server->name,
            ])
            ->values();

        $cs2Servers = $onlineServers->where('game', 'cs2')->count();
        $cs16Servers = $onlineServers->where('game', 'cs16')->count();
        $totalPlayers = $onlineServers->sum(fn (Server $server) => (int) $server->latestStatus->players);
        $reportHeader = now()->timezone(config('app.timezone'))->format('Y-m-d H:i')
            .' · '.$onlineServers->count().' сервер · '.$totalPlayers." тоглогч\n"
            .'CS2: '.$cs2Servers.' · CS 1.6: '.$cs16Servers;
        $serverBlocks = $this->serverBlocks($onlineServers);
        $messages = $serverBlocks ? $this->chunkMessages($reportHeader, $serverBlocks) : [$reportHeader."\nОдоогоор шинэ төлөвтэй онлайн сервер алга."];

        try {
            $previousMessageIds = Cache::get('speedmn.discord.players.message_ids', []);
            if (! is_array($previousMessageIds)) {
                $previousMessageIds = [];
            }

            $messageIds = [];
            foreach ($messages as $index => $message) {
                $payload = [
                    'embeds' => [[
                        'title' => 'Speed.mn · Онлайн тоглогчид',
                        'description' => $message,
                        'color' => 0xE84D6A,
                        'timestamp' => now()->toIso8601String(),
                        'footer' => ['text' => '10 минут тутам шинэчлэгдэнэ'],
                    ]],
                    'allowed_mentions' => ['parse' => []],
                ];
                $messageId = $previousMessageIds[$index] ?? null;

                if ($messageId) {
                    $response = Http::timeout(10)->patch($this->messageUrl($webhook, (string) $messageId), $payload);
                    if ($response->status() === 404) {
                        $messageId = null;
                    } else {
                        $response->throw();
                    }
                }

                if (! $messageId) {
                    $postUrl = $webhook.(str_contains($webhook, '?') ? '&' : '?').'wait=true';
                    $response = Http::timeout(10)->post($postUrl, $payload)->throw();
                    $messageId = $response->json('id');
                    if (! is_string($messageId) || $messageId === '') {
                        throw new \RuntimeException('Discord did not return a message ID for the player report.');
                    }
                }

                $messageIds[] = $messageId;
            }

            foreach (array_slice($previousMessageIds, count($messageIds)) as $obsoleteMessageId) {
                $response = Http::timeout(10)->delete($this->messageUrl($webhook, (string) $obsoleteMessageId));
                if ($response->status() !== 404) {
                    $response->throw();
                }
            }

            Cache::forever('speedmn.discord.players.message_ids', $messageIds);
        } catch (Throwable $exception) {
            Log::warning('Could not send Discord player report', ['error' => $exception->getMessage()]);
            $this->error('Could not send Discord player report; details were logged.');

            return self::FAILURE;
        }

        $this->info('Updated Discord player report for '.$onlineServers->count().' online server(s).');

        return self::SUCCESS;
    }

    private function serverBlocks(Collection $servers): array
    {
        $blocks = [];
        $previousGame = null;
        foreach ($servers as $server) {
            $status = $server->latestStatus;
            $game = $server->game === 'cs2' ? 'CS2' : 'CS 1.6';
            $capacity = $status->max_players ?: $server->max_players ?: '—';
            $serverName = Str::limit(str_replace(['`', '*', '_', '~', '|'], ' ', Str::squish($server->name)), 80, '…');
            $map = Str::limit(str_replace('`', ' ', (string) ($status->map ?: 'map тодорхойгүй')), 40, '…');
            $groupHeading = $server->game !== $previousGame ? "### {$game}\n" : '';
            $previousGame = $server->game;
            $block = $groupHeading."**{$serverName}**\n`{$server->address}` · `{$map}` · **{$status->players}/{$capacity}**";
            $players = collect($status->player_list ?? []);

            if ($players->isEmpty()) {
                $block .= "\nТоглогчдын нэрийн жагсаалт ирээгүй.";
            } else {
                foreach ($players->take(20) as $player) {
                    $name = Str::squish(preg_replace('/[\x00-\x1F\x7F]/u', ' ', (string) ($player['name'] ?? 'Тоглогч')) ?? 'Тоглогч');
                    $name = Str::limit(str_replace(['`', '*', '_', '~', '|'], ' ', $name), 48, '…');
                    $duration = max(0, (int) ($player['duration'] ?? 0));
                    $block .= "\n• {$name} · ".(int) ($player['score'] ?? 0)." оноо · ".gmdate('H:i:s', $duration);
                }

                if ($players->count() > 20) {
                    $block .= "\n… мөн ".($players->count() - 20).' тоглогч';
                }
            }

            $blocks[] = $block;
        }

        return $blocks;
    }

    private function chunkMessages(string $header, array $blocks): array
    {
        $messages = [];
        $message = $header;
        foreach ($blocks as $block) {
            if (mb_strlen($message."\n\n".$block) > 3500 && $message !== $header) {
                $messages[] = $message;
                $message = $header;
            }

            if (mb_strlen($message."\n\n".$block) > 3800) {
                $block = mb_substr($block, 0, 3800 - mb_strlen($header) - 2).'…';
            }

            $message .= "\n\n".$block;
        }

        if ($message !== $header || $blocks === []) {
            $messages[] = $message;
        }

        return $messages;
    }

    private function messageUrl(string $webhook, string $messageId): string
    {
        $queryPosition = strpos($webhook, '?');
        $baseUrl = $queryPosition === false ? $webhook : substr($webhook, 0, $queryPosition);
        $query = $queryPosition === false ? '' : substr($webhook, $queryPosition);

        return rtrim($baseUrl, '/').'/messages/'.rawurlencode($messageId).$query;
    }
}