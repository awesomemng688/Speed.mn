<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServerResource;
use App\Models\Server;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

class ServerController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $servers = Server::where('enabled', true)
            ->when($request->game, fn ($query, $game) => $query->where('game', $game))
            ->with('latestStatus')
            ->paginate(24);

        return ServerResource::collection($servers);
    }

    public function show(Server $server): ServerResource
    {
        return new ServerResource($server->load('latestStatus'));
    }

    public function monitoring(Server $server): \Illuminate\Http\JsonResponse
    {
        $history = $server->statuses()
            ->where('created_at', '>=', Carbon::now()->subDay())
            ->oldest()
            ->get(['created_at', 'online', 'players', 'response_time']);
        $checks = $history->count();
        $onlineHistory = $history->where('online', true);

        return response()->json([
            'uptime_percentage' => $checks ? round($history->where('online', true)->count() / $checks * 100, 2) : null,
            'average_response_time' => $onlineHistory->whereNotNull('response_time')->avg('response_time'),
            'peak_players' => $history->max('players') ?? 0,
            'checks' => $checks,
            'history' => $history,
            'offline_history' => $history->where('online', false)->values(),
        ]);
    }
}
