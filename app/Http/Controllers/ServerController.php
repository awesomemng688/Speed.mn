<?php

namespace App\Http\Controllers;

use App\Models\Server;
use Illuminate\View\View;
use Illuminate\Support\Carbon;

class ServerController extends Controller
{
    public function index(?string $game = null): View
    {
        $servers = Server::query()
            ->where('enabled', true)
            ->when(in_array($game, ['cs2', 'cs16'], true), fn ($query) => $query->where('game', $game))
            ->with('latestStatus')
            ->orderBy('game')
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return view('servers.index', compact('servers', 'game'));
    }

    public function show(Server $server): View
    {
        abort_unless($server->enabled, 404);
        $server->load('latestStatus');
        $history = $server->statuses()
            ->where('created_at', '>=', Carbon::now()->subDay())
            ->oldest()
            ->get();
        $checks = $history->count();
        $uptime = $checks ? round($history->where('online', true)->count() / $checks * 100, 2) : null;
        $offlineHistory = $history->where('online', false)->values();

        return view('servers.show', compact('server', 'history', 'uptime', 'offlineHistory'));
    }
}
