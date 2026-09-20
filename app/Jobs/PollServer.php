<?php

namespace App\Jobs;

use App\Models\Server;
use App\Models\ServerStatus;
use App\Services\ServerQueryService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

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

        ServerStatus::create(array_merge(['server_id' => $server->id], $query->query($server)));
    }
}
