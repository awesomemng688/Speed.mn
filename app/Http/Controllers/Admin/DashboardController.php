<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Server;
use App\Models\User;
use App\Services\NetworkStats;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(NetworkStats $stats): View
    {
        return view('admin.dashboard', [
            'stats' => $stats->summary(now()->subDay()),
            'users' => User::count(),
            'disabledServers' => Server::where('enabled', false)->count(),
        ]);
    }
}
