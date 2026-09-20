<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreServerRequest;
use App\Http\Requests\UpdateServerRequest;
use App\Models\Server;
use App\Services\ServerQueryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ServerController extends Controller
{
    public function index(): View
    {
        $servers = Server::query()
            ->with('latestStatus')
            ->orderBy('game')
            ->orderBy('name')
            ->paginate(20);

        return view('admin.servers.index', compact('servers'));
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
            : 'Connection failed or the server did not respond.';

        return back()->with($result['online'] ? 'connection_success' : 'connection_error', $message);
    }
}
