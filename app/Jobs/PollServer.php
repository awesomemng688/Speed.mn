<?php

namespace App\Jobs;

use App\Models\Server;
use App\Models\ServerStatus;
use App\Models\User;
use App\Notifications\ServerStatusChanged;
use App\Services\ServerQueryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class PollServer implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $serverId) {}

    public function handle(ServerQueryService $query): void
    {
        $server = Server::find($this->serverId);
        if (!$server || !$server->enabled) {
            return;
        }

        $polledAt = now();
        $result = $query->query($server);
        $querySucceeded = (bool) ($result['query_succeeded'] ?? ($result['online'] ?? false));
        $queryError = $result['query_error'] ?? null;
        unset($result['query_succeeded'], $result['query_error']);
        $result['max_players'] ??= $server->max_players;
        $recentStatuses = $server->statuses()->orderByDesc('id')->limit(3)->get();

        ServerStatus::create(array_merge(
            ['server_id' => $server->id, 'created_at' => $polledAt],
            $result,
        ));

        $newStatus = (bool) ($result['online'] ?? false);
        $confirmedOffline = ! $querySucceeded
            && ! $newStatus
            && isset($recentStatuses[0], $recentStatuses[1])
            && ! $recentStatuses[0]->online
            && $recentStatuses[1]->online;
        $confirmedRecovery = $querySucceeded
            && $newStatus
            && isset($recentStatuses[0], $recentStatuses[1], $recentStatuses[2])
            && ! $recentStatuses[0]->online
            && ! $recentStatuses[1]->online;

        if ($confirmedOffline || $confirmedRecovery) {
            try {
                $server->favoritedBy()->get()->each(fn (User $user) => $user->notify(new ServerStatusChanged(
                    $server->id,
                    $server->name,
                    $server->address,
                    $confirmedRecovery,
                )));
            } catch (Throwable $exception) {
                Log::warning('Could not notify users about a favorite server status change', [
                    'server_id' => $server->id,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $server->forceFill([
            'last_polled_at' => $polledAt,
            'last_successful_poll_at' => $querySucceeded ? $polledAt : $server->last_successful_poll_at,
            'last_query_error' => $querySucceeded ? null : Str::limit($queryError ?: 'Server query failed without an error message.', 2000),
        ])->save();

        $this->storeHeartbeat('speedmn.poll.last_completed_at', $polledAt);
    }

    public function failed(Throwable $exception): void
    {
        $failedAt = now();
        $server = Server::find($this->serverId);

        $server?->forceFill([
            'last_polled_at' => $failedAt,
            'last_query_error' => Str::limit('Polling job failed: '.$exception->getMessage(), 2000),
        ])->save();

        $this->storeHeartbeat('speedmn.poll.last_failed_at', $failedAt);
    }

    private function storeHeartbeat(string $key, $timestamp): void
    {
        try {
            Cache::put($key, $timestamp->toIso8601String(), now()->addDay());
        } catch (Throwable $exception) {
            Log::warning('Could not store poll worker heartbeat', [
                'server_id' => $this->serverId,
                'key' => $key,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
