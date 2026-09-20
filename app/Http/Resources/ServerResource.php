<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $status = $this->latestStatus;
        return [
            'id' => $this->id,
            'name' => $this->name,
            'game' => $this->game,
            'address' => $this->address,
            'region' => $this->region,
            'country' => $this->country,
            'online' => (bool) ($status?->online ?? false),
            'players' => $status?->players ?? 0,
            'max_players' => $status?->max_players ?: $this->max_players,
            'map' => $status?->map,
            'version' => $status?->version,
            'vac' => $status?->vac,
            'bots' => $status?->bots,
            'players_list' => $status?->player_list,
            'ping' => $status?->response_time,
            'last_update' => $status?->created_at,
        ];
    }
}
