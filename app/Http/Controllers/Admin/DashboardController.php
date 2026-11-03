<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Server;
use App\Models\ServerStatus;
use App\Models\ServerSubmission;
use App\Models\User;
use App\Models\VipMember;
use App\Services\NetworkStats;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(NetworkStats $stats): View
    {
        $attentionServers = Server::query()->where('enabled', true)->with('latestStatus')->get()
            ->filter(fn (Server $server) => ServerStatus::stateOf($server->latestStatus) !== 'online')
            ->values();
        $failedJobs = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;

        return view('admin.dashboard', [
            'stats' => $stats->summary(now()->subDay()),
            'users' => User::count(),
            'disabledServers' => Server::where('enabled', false)->count(),
            'pendingSubmissions' => ServerSubmission::query()->where('status', 'pending')->count(),
            'expiringVip' => VipMember::query()->whereNotNull('expires_at')->whereBetween('expires_at', [now()->toDateString(), now()->addDays(14)->toDateString()])->count(),
            'attentionServers' => $attentionServers,
            'failedJobs' => $failedJobs,
        ]);
    }
}
