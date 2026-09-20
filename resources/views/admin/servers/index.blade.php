@extends('layouts.app')
@section('title', 'Manage servers — Speed.mn')
@section('content')
<section class="page-head"><div class="shell"><p class="eyebrow">ADMINISTRATION</p><div class="section-heading"><h1>Servers</h1><a class="button button-primary" href="{{ route('admin.servers.create') }}">Add server</a></div><p class="muted">Manage the public server directory and connectivity.</p></div></section>
<section class="shell section admin-section">
    @if(session('status'))<div class="admin-alert success">{{ session('status') }}</div>@endif
    @if(session('connection_success'))<div class="admin-alert success">{{ session('connection_success') }}</div>@endif
    @if(session('connection_error'))<div class="admin-alert error">{{ session('connection_error') }}</div>@endif
    <div class="admin-summary">
        <div class="panel"><small>Total servers</small><strong>{{ $summary['total'] }}</strong></div>
        <div class="panel"><small>Enabled</small><strong>{{ $summary['enabled'] }}</strong></div>
        <div class="panel"><small>Online now</small><strong class="admin-online">{{ $summary['online'] }}</strong></div>
        <div class="panel"><small>Players online</small><strong>{{ $summary['players'] }}</strong></div>
    </div>
    <div class="panel admin-table-wrap"><table class="admin-table"><thead><tr><th>Server</th><th>Game</th><th>Address</th><th>Status</th><th>Visibility</th><th>Actions</th></tr></thead><tbody>
    @forelse($servers as $server)<tr><td><strong>{{ $server->name }}</strong><small>{{ $server->region ?: 'No region' }}</small></td><td>{{ strtoupper($server->game) }}</td><td>{{ $server->address }}</td><td><span class="status {{ $server->latestStatus?->online ? 'online' : '' }}"><i></i>{{ $server->latestStatus?->online ? 'Online' : 'Offline' }}</span></td><td><span class="admin-badge {{ $server->enabled ? 'enabled' : 'disabled' }}">{{ $server->enabled ? 'Enabled' : 'Disabled' }}</span></td><td><div class="admin-actions"><a class="text-link" href="{{ route('admin.servers.edit', $server) }}">Edit</a><form method="POST" action="{{ route('admin.servers.toggle', $server) }}">@csrf @method('PATCH')<button class="text-button" type="submit">{{ $server->enabled ? 'Disable' : 'Enable' }}</button></form><form method="POST" action="{{ route('admin.servers.test', $server) }}">@csrf<button class="text-button" type="submit">Test</button></form><form method="POST" action="{{ route('admin.servers.destroy', $server) }}" onsubmit="return confirm('Delete this server and its status history?')">@csrf @method('DELETE')<button class="text-button danger" type="submit">Delete</button></form></div></td></tr>@empty<tr><td colspan="6" class="empty">No servers configured.</td></tr>@endforelse
    </tbody></table></div>{{ $servers->links() }}
</section>
@endsection
