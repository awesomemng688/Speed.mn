<?php

namespace App\Console\Commands;

use App\Models\Server;
use App\Models\ServerStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
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
            ->values();

        $reportHeader = 'Speed.mn · '.now()->timezone(config('app.timezone'))->format('Y-m-d H:i').' · Онлайн серверийн тоглогчид';
        $serverBlocks = $this->serverBlocks($onlineServers);
        $messages = $serverBlocks ? $this->chunkMessages($reportHeader, $serverBlocks) : [$reportHeader."\nОдоогоор шинэ төлөвтэй онлайн сервер алга."];

        try {
            foreach ($messages as $message) {
                Http::timeout(10)->post($webhook, [
                    'content' => $message,
                    'allowed_mentions' => ['parse' => []],
                ])->throw();
            }
        } catch (Throwable $exception) {
            Log::warning('Could not send Discord player report', ['error' => $exception->getMessage()]);
            $this->error('Could not send Discord player report; details were logged.');

            return self::FAILURE;
        }

        $this->info('Sent player reports for '.$onlineServers->count().' online server(s).');

        return self::SUCCESS;
    }

    private function serverBlocks(Collection $servers): array
    {
        $blocks = [];
        foreach ($servers as $server) {
            $status = $server->latestStatus;
            $game = $server->game === 'cs2' ? 'CS2' : 'CS 1.6';
            $capacity = $status->max_players ?: $server->max_players ?: '—';
            $block = "**{$game} · {$server->name}**\n{$server->address} · ".($status->map ?: 'map тодорхойгүй')." · {$status->players}/{$capacity}";
            $players = collect($status->player_list ?? []);

            if ($players->isEmpty()) {
                $block .= "\nТоглогчдын нэрийн жагсаалт ирээгүй.";
            } else {
                foreach ($players->take(20) as $player) {
                    $name = Str::squish(preg_replace('/[\x00-\x1F\x7F]/u', ' ', (string) ($player['name'] ?? 'Тоглогч')) ?? 'Тоглогч');
                    $name = Str::limit($name, 48, '…');
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
            if (mb_strlen($message."\n\n".$block) > 1800 && $message !== $header) {
                $messages[] = $message;
                $message = $header;
            }

            if (mb_strlen($message."\n\n".$block) > 1900) {
                $block = mb_substr($block, 0, 1900 - mb_strlen($header) - 2).'…';
            }

            $message .= "\n\n".$block;
        }

        if ($message !== $header || $blocks === []) {
            $messages[] = $message;
        }

        return $messages;
    }
}