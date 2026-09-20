@php($status = $server->latestStatus)
<article class="server-card" data-server-card data-name="{{ strtolower($server->name.' '.$server->address) }}" data-game="{{ $server->game }}">
    <div class="server-card-top"><span class="game-pill {{ $server->game }}">{{ $server->game === 'cs2' ? 'CS2' : 'CS 1.6' }}</span><span class="status {{ $status?->online ? 'online' : 'offline' }}"><i></i>{{ $status?->online ? 'ONLINE' : 'OFFLINE' }}</span></div>
    <h3>{{ $server->name }}</h3>
    <p class="muted">{{ $server->region ?: 'Mongolia' }} · {{ $server->address }}</p>
    <div class="server-metrics"><div><strong>{{ $status?->players ?? 0 }}/{{ $status?->max_players ?: $server->max_players }}</strong><small>PLAYERS</small></div><div><strong>{{ $status?->map ?: '—' }}</strong><small>MAP</small></div><div><strong>{{ $status?->response_time ? $status->response_time.'ms' : '—' }}</strong><small>PING</small></div></div>
    <div class="card-actions"><a class="button button-primary" href="steam://connect/{{ $server->address }}" aria-label="Connect to {{ $server->name }}">Connect</a><a class="button button-ghost" href="{{ route('servers.show', $server) }}">Details</a></div>
    @if($status?->created_at)<small class="updated-at">Updated {{ $status->created_at->diffForHumans() }}</small>@endif
</article>
