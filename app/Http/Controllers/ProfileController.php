<?php

namespace App\Http\Controllers;

use App\Models\Server;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(): View
    {
        $user = Auth::user();
        $servers = Server::query()
            ->where('enabled', true)
            ->with('latestStatus')
            ->orderByDesc('updated_at')
            ->take(3)
            ->get();

        $onlineServers = $servers->filter(fn (Server $server) => $server->latestStatus?->online)->count();
        $playersOnline = $servers->sum(fn (Server $server) => $server->latestStatus?->players ?? 0);

        return view('profile', [
            'user' => $user,
            'servers' => $servers,
            'networkSnapshot' => [
                'online_servers' => $onlineServers,
                'players_online' => $playersOnline,
            ],
        ]);
    }
}
