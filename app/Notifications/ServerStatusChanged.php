<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ServerStatusChanged extends Notification
{
    use Queueable;

    public function __construct(
        public int $serverId,
        public string $serverName,
        public string $address,
        public bool $online,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'server_id' => $this->serverId,
            'server_name' => $this->serverName,
            'address' => $this->address,
            'status' => $this->online ? 'online' : 'offline',
            'changed_at' => now()->toIso8601String(),
        ];
    }
}