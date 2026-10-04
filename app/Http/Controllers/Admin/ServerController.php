<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreServerRequest;
use App\Http\Requests\UpdateServerRequest;
use App\Models\Server;
use App\Models\ServerStatus;
use App\Services\ServerQueryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class ServerController extends Controller
{
    public function index(): View
    {
        $servers = Server::query()
            ->with('latestStatus')
            ->orderBy('game')
            ->orderBy('name')
            ->paginate(20);

        $enabledServers = Server::where('enabled', true)->with('latestStatus')->get();
        $summary = [
            'total' => Server::count(),
            'enabled' => $enabledServers->count(),
            'online' => $enabledServers->filter(fn (Server $server) => ServerStatus::stateOf($server->latestStatus) === 'online')->count(),
            'players' => $enabledServers->sum(fn (Server $server) => ServerStatus::stateOf($server->latestStatus) === 'online' ? ($server->latestStatus->players ?? 0) : 0),
        ];

        $lastDispatchedAt = $this->cacheTimestamp('speedmn.poll.last_dispatched_at');
        $lastCompletedAt = $this->cacheTimestamp('speedmn.poll.last_completed_at');
        $lastFailedAt = $this->cacheTimestamp('speedmn.poll.last_failed_at');
        $pollMonitor = [
            'last_dispatched_at' => $lastDispatchedAt,
            'last_completed_at' => $lastCompletedAt,
            'last_failed_at' => $lastFailedAt,
            'scheduler_healthy' => $summary['enabled'] === 0 || ($lastDispatchedAt?->gte(now()->subSeconds(75)) ?? false),
            'worker_healthy' => $summary['enabled'] === 0 || ($lastCompletedAt?->gte(now()->subSeconds(120)) ?? false),
            'stale_servers' => Server::where('enabled', true)
                ->where(fn ($query) => $query->whereNull('last_polled_at')->orWhere('last_polled_at', '<', now()->subMinutes(2)))
                ->count(),
            'failed_jobs' => DB::table('failed_jobs')->where('failed_at', '>=', now()->subHour())->count(),
        ];

        return view('admin.servers.index', compact('servers', 'summary', 'pollMonitor'));
    }

    public function create(): View
    {
        return view('admin.servers.create', ['server' => new Server(['enabled' => true, 'max_players' => 0, 'query_type' => 'a2s'])]);
    }

    public function store(StoreServerRequest $request): RedirectResponse
    {
        $server = Server::create($request->validated());

        return redirect()->route('admin.servers.index')->with('status', "Server {$server->name} was created.");
    }

    public function edit(Server $server): View
    {
        return view('admin.servers.edit', compact('server'));
    }

    public function update(UpdateServerRequest $request, Server $server): RedirectResponse
    {
        $server->update($request->validated());

        return redirect()->route('admin.servers.index')->with('status', "Server {$server->name} was updated.");
    }

    public function destroy(Server $server): RedirectResponse
    {
        $name = $server->name;
        $server->delete();

        return redirect()->route('admin.servers.index')->with('status', "Server {$name} was deleted.");
    }

    public function toggle(Server $server): RedirectResponse
    {
        $server->update(['enabled' => ! $server->enabled]);
        $state = $server->enabled ? 'enabled' : 'disabled';

        return back()->with('status', "Server {$server->name} is now {$state}.");
    }

    public function test(Server $server, ServerQueryService $queryService): RedirectResponse
    {
        $result = $queryService->query($server);
        $message = $result['online']
            ? "Connection successful ({$result['response_time']} ms)."
            : ($result['query_error'] ?? 'Connection failed or the server did not respond.');

        return back()->with($result['online'] ? 'connection_success' : 'connection_error', $message);
    }

    private function cacheTimestamp(string $key): ?Carbon
    {
        try {
            $value = Cache::get($key);
        } catch (Throwable $exception) {
            Log::warning('Could not read poll heartbeat', [
                'key' => $key,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }

        return $value ? Carbon::parse($value) : null;
    }
}
