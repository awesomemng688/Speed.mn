<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreServerRequest;
use App\Http\Requests\UpdateServerRequest;
use App\Jobs\PollServer;
use App\Models\Server;
use App\Models\ServerStatus;
use App\Services\AdminAuditLogger;
use App\Services\ServerQueryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class ServerController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'game' => ['nullable', 'in:cs2,cs16'],
            'status' => ['nullable', 'in:online,offline,stale,unknown'],
            'poll' => ['nullable', 'in:fresh,stale,never'],
            'error' => ['nullable', 'in:yes,no'],
            'sort' => ['nullable', 'in:poll_newest,poll_oldest'],
        ]);
        $freshSince = ServerStatus::freshSince();

        $servers = Server::query()
            ->with('latestStatus')
            ->when($filters['search'] ?? null, function ($query, string $term): void {
                $query->where(function ($query) use ($term): void {
                    $query->where('name', 'like', "%{$term}%")
                        ->orWhere('ip', 'like', "%{$term}%")
                        ->orWhere('port', 'like', "%{$term}%");
                });
            })
            ->when($filters['game'] ?? null, fn ($query, string $game) => $query->where('game', $game))
            ->when($filters['status'] ?? null, function ($query, string $status) use ($freshSince): void {
                match ($status) {
                    'online' => $query->whereHas('latestStatus', fn ($latest) => $latest->where('created_at', '>=', $freshSince)->where('online', true)),
                    'offline' => $query->whereHas('latestStatus', fn ($latest) => $latest->where('created_at', '>=', $freshSince)->where('online', false)),
                    'stale' => $query->whereHas('latestStatus', fn ($latest) => $latest->where('created_at', '<', $freshSince)),
                    'unknown' => $query->whereDoesntHave('latestStatus'),
                };
            })
            ->when(($filters['poll'] ?? null) === 'fresh', fn ($query) => $query->where('last_polled_at', '>=', now()->subMinutes(2)))
            ->when(($filters['poll'] ?? null) === 'stale', fn ($query) => $query->where(fn ($query) => $query->whereNull('last_polled_at')->orWhere('last_polled_at', '<', now()->subMinutes(2))))
            ->when(($filters['poll'] ?? null) === 'never', fn ($query) => $query->whereNull('last_polled_at'))
            ->when(($filters['error'] ?? null) === 'yes', fn ($query) => $query->whereNotNull('last_query_error')->where('last_query_error', '<>', ''))
            ->when(($filters['error'] ?? null) === 'no', fn ($query) => $query->where(fn ($query) => $query->whereNull('last_query_error')->orWhere('last_query_error', '')))
            ->when(($filters['sort'] ?? null) === 'poll_newest', fn ($query) => $query->orderByRaw('(last_polled_at IS NULL) ASC')->orderByDesc('last_polled_at'))
            ->when(($filters['sort'] ?? null) === 'poll_oldest', fn ($query) => $query->orderByRaw('(last_polled_at IS NOT NULL) ASC')->orderBy('last_polled_at'))
            ->when(! in_array($filters['sort'] ?? null, ['poll_newest', 'poll_oldest'], true), fn ($query) => $query->orderBy('game')->orderBy('name'))
            ->paginate(20)
            ->withQueryString();

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

        return view('admin.servers.index', compact('servers', 'summary', 'pollMonitor', 'filters'));
    }

    public function create(): View
    {
        return view('admin.servers.create', ['server' => new Server(['enabled' => true, 'max_players' => 0, 'query_type' => 'a2s'])]);
    }

    public function store(StoreServerRequest $request, AdminAuditLogger $audit): RedirectResponse
    {
        $server = Server::create($request->validated());
        $audit->record($request, 'server.created', $server, [
            'fields' => array_keys($request->validated()),
        ]);

        return redirect()->route('admin.servers.index')->with('status', "Server {$server->name} was created.");
    }

    public function edit(Server $server): View
    {
        return view('admin.servers.edit', compact('server'));
    }

    public function update(UpdateServerRequest $request, Server $server, AdminAuditLogger $audit): RedirectResponse
    {
        $server->fill($request->validated());
        $changedFields = array_keys($server->getDirty());
        $server->save();
        $audit->record($request, 'server.updated', $server, [
            'changed_fields' => $changedFields,
        ]);

        return redirect()->route('admin.servers.index')->with('status', "Server {$server->name} was updated.");
    }

    public function destroy(Server $server, Request $request, AdminAuditLogger $audit): RedirectResponse
    {
        $name = $server->name;
        $audit->record($request, 'server.deleted', $server);
        $server->delete();

        return redirect()->route('admin.servers.index')->with('status', "Server {$name} was deleted.");
    }

    public function toggle(Server $server, Request $request, AdminAuditLogger $audit): RedirectResponse
    {
        $server->update(['enabled' => ! $server->enabled]);
        $state = $server->enabled ? 'enabled' : 'disabled';
        $audit->record($request, $server->enabled ? 'server.enabled' : 'server.disabled', $server);

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

    public function pollNow(Server $server, Request $request, AdminAuditLogger $audit): RedirectResponse
    {
        abort_unless($server->enabled, 422, 'Enable the server before polling it.');

        PollServer::dispatch($server->id);
        $audit->record($request, 'server.poll_dispatched', $server);

        return back()->with('status', "A poll was queued for {$server->name}.");
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
