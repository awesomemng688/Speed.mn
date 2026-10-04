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
        if ($onlineServers->isEmpty()) {
            $reportHeader .= "\nОдоогоор шинэ төлөвтэй онлайн сервер алга.";
        }
        $messages = $this->embedMessages($reportHeader, $onlineServers);

        try {
            $previousMessageIds = Cache::get('speedmn.discord.players.message_ids', []);
            if (! is_array($previousMessageIds)) {
                $previousMessageIds = [];
            }

            $messageIds = [];
            foreach ($messages as $index => $embeds) {
                $payload = [
                    'embeds' => $embeds,
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

    private function embedMessages(string $summary, Collection $servers): array
    {
        $summaryEmbed = [
            'title' => 'Speed.mn · Онлайн серверүүд',
            'description' => $summary,
            'color' => 0xE84D6A,
            'timestamp' => now()->toIso8601String(),
            'footer' => ['text' => '10 минут тутам шинэчлэгдэнэ · Тоглогчийн нэр харуулахгүй'],
        ];
        $messages = [];
        $embeds = [$summaryEmbed];

        foreach ($servers as $server) {
            if (count($embeds) === 10) {
                $messages[] = $embeds;
                $embeds = [$summaryEmbed];
            }

            $status = $server->latestStatus;
            $game = $server->game === 'cs2' ? 'CS2' : 'CS 1.6';
            $capacity = $status->max_players ?: $server->max_players ?: '—';
            $serverName = Str::limit(str_replace(['`', '*', '_', '~', '|'], ' ', Str::squish($server->name)), 80, '…');
            $map = Str::limit(str_replace('`', ' ', (string) ($status->map ?: 'тодорхойгүй')), 40, '…');
            $embed = [
                'title' => $game.' · '.$serverName,
                'color' => $game === 'CS2' ? 0x53A6FF : 0xF0A04B,
                'fields' => [
                    ['name' => 'Тоглогч', 'value' => $status->players.'/'.$capacity, 'inline' => true],
                    ['name' => 'Map', 'value' => $map, 'inline' => true],
                    ['name' => 'Хаяг', 'value' => $server->address, 'inline' => true],
                ],
            ];

            if ($status->map) {
                $mapGame = $server->game === 'cs2' ? 'csgo' : 'css';
                $embed['thumbnail'] = [
                    'url' => 'https://image.gametracker.com/images/maps/160x120/'.$mapGame.'/'.rawurlencode($status->map).'.jpg',
                ];
            }

            $embeds[] = $embed;
        }

        $messages[] = $embeds;

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