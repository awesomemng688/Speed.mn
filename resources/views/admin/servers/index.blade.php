@extends('layouts.app')
@section('title', 'Manage servers — Speed.mn')
@section('content')
<section class="page-head"><div class="shell"><p class="eyebrow">ADMINISTRATION</p><div class="section-heading"><h1>Servers</h1><div class="admin-actions"><a class="button button-ghost" href="{{ route('admin.users.index') }}">Administrators</a><a class="button button-primary" href="{{ route('admin.servers.create') }}">Add server</a></div></div><p class="muted">Manage the public server directory and connectivity.</p></div></section>
<section class="shell section admin-section">
    @if(session('status'))<div class="admin-alert success">{{ session('status') }}</div>@endif
    @if(session('connection_success'))<div class="admin-alert success">{{ session('connection_success') }}</div>@endif
    @if(session('connection_error'))<div class="admin-alert error">{{ session('connection_error') }}</div>@endif
    <div class="admin-alert {{ $pollMonitor['scheduler_healthy'] && $pollMonitor['worker_healthy'] && $pollMonitor['failed_jobs'] === 0 ? 'success' : 'error' }}" role="status">
        Poll scheduler: <strong>{{ $pollMonitor['scheduler_healthy'] ? 'running' : 'not detected' }}</strong>
        · Queue worker: <strong>{{ $pollMonitor['worker_healthy'] ? 'processing' : 'no recent jobs' }}</strong>
        · Stale servers: <strong>{{ $pollMonitor['stale_servers'] }}</strong>
        · Failed jobs (1h): <strong>{{ $pollMonitor['failed_jobs'] }}</strong>
        <small>Last dispatch: {{ $pollMonitor['last_dispatched_at']?->diffForHumans() ?? 'never' }} · Last completed: {{ $pollMonitor['last_completed_at']?->diffForHumans() ?? 'never' }}</small>
    </div>
    @if($pollMonitor['last_failed_at'])<div class="admin-alert error" role="alert">Last poll job failure: {{ $pollMonitor['last_failed_at']->diffForHumans() }}</div>@endif
    <div class="admin-summary">
        <div class="panel"><small>Total servers</small><strong>{{ $summary['total'] }}</strong></div>
        <div class="panel"><small>Enabled</small><strong>{{ $summary['enabled'] }}</strong></div>
        <div class="panel"><small>Online now</small><strong class="admin-online">{{ $summary['online'] }}</strong></div>
        <div class="panel"><small>Players online</small><strong>{{ $summary['players'] }}</strong></div>
    </div>
    <div class="panel admin-table-wrap"><table class="admin-table"><thead><tr><th>Server</th><th>Game</th><th>Address</th><th>Status</th><th>Last poll</th><th>Last success</th><th>Latest query error</th><th>Visibility</th><th>Actions</th></tr></thead><tbody>
    @forelse($servers as $server)
        @php($statusState = \App\Models\ServerStatus::stateOf($server->latestStatus))
        <tr><td><strong>{{ $server->name }}</strong><small>{{ $server->region ?: 'No region' }}</small></td><td>{{ strtoupper($server->game) }}</td><td>{{ $server->address }}</td><td><span class="status {{ $statusState }}"><i></i>{{ ucfirst($statusState) }}</span></td><td>{{ $server->last_polled_at?->diffForHumans() ?? 'Never' }}</td><td>{{ $server->last_successful_poll_at?->diffForHumans() ?? 'Never' }}</td><td><small>{{ $server->last_query_error ?: '—' }}</small></td><td><span class="admin-badge {{ $server->enabled ? 'enabled' : 'disabled' }}">{{ $server->enabled ? 'Enabled' : 'Disabled' }}</span></td><td><div class="admin-actions"><a class="text-link" href="{{ route('admin.servers.edit', $server) }}">Edit</a><form method="POST" action="{{ route('admin.servers.toggle', $server) }}">@csrf @method('PATCH')<button class="text-button" type="submit">{{ $server->enabled ? 'Disable' : 'Enable' }}</button></form><form method="POST" action="{{ route('admin.servers.test', $server) }}">@csrf<button class="text-button" type="submit">Test</button></form><form method="POST" action="{{ route('admin.servers.destroy', $server) }}" onsubmit="return confirm('Delete this server and its status history?')">@csrf @method('DELETE')<button class="text-button danger" type="submit">Delete</button></form></div></td></tr>
    @empty<tr><td colspan="9" class="empty">No servers configured.</td></tr>@endforelse
    </tbody></table></div>{{ $servers->links() }}
</section>
@endsection
