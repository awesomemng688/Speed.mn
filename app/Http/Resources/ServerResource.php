<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $status = $this->latestStatus;
        $data = [
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

        // The relationship is loaded only for detail responses so collection
        // responses keep their existing payload and query count.
        if ($this->relationLoaded('statuses')) {
            $history = $this->statuses->sortBy('created_at')->values();
            $onlineHistory = $history->where('online', true);
            $checks = $history->count();

            $data['monitoring'] = [
                'uptime_percentage' => $checks ? round($onlineHistory->count() / $checks * 100, 2) : null,
                'average_response_time' => $onlineHistory->whereNotNull('response_time')->avg('response_time'),
                'peak_players' => $history->max('players') ?? 0,
                'checks' => $checks,
                'history' => $history->map($this->monitoringPoint(...))->values(),
                'offline_history' => $history->where('online', false)->map($this->monitoringPoint(...))->values(),
            ];
        }

        return $data;
    }

    private function monitoringPoint($point): array
    {
        return [
            'created_at' => $point->created_at,
            'online' => $point->online,
            'players' => $point->players,
            'response_time' => $point->response_time,
        ];
    }
}
