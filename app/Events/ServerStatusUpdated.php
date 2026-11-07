<?php

namespace App\Events;

use App\Models\Server;
use App\Models\ServerStatus;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ServerStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Server $server,
        public ServerStatus $status,
        public bool $isOnline
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('server-status.'.$this->server->id),
            new Channel('server-status-all'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'server.status.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'server_id' => $this->server->id,
            'server_name' => $this->server->name,
            'server_address' => $this->server->address,
            'game' => $this->server->game,
            'online' => $this->isOnline,
            'players' => $this->status->players ?? 0,
            'max_players' => $this->status->max_players ?? $this->server->max_players,
            'map' => $this->status->map,
            'response_time' => $this->status->response_time,
            'updated_at' => $this->status->created_at?->toISOString(),
        ];
    }
}