@php($status = $server->latestStatus)
@php($map = $status?->map ?: 'unknown')
@php($mapImage = 'https://image.gametracker.com/images/maps/160x120/'.($server->game === 'cs2' ? 'csgo' : 'css').'/'.$map.'.jpg')
<article class="server-card" data-server-card data-name="{{ strtolower($server->name.' '.$server->address) }}" data-game="{{ $server->game }}">
    <div class="server-card-cover">
        <img src="{{ $mapImage }}" alt="{{ $map }} map thumbnail" loading="lazy" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'">
        <div class="server-card-cover-fallback" style="display:none"><span>{{ $server->game === 'cs2' ? 'CS2' : 'CS 1.6' }}</span><strong>{{ $map }}</strong></div>
        <div class="server-card-overlay"><span class="game-pill {{ $server->game }}">{{ $server->game === 'cs2' ? 'CS2' : 'CS 1.6' }}</span><span class="status {{ $status?->online ? 'online' : 'offline' }}"><i></i>{{ $status?->online ? 'LIVE' : 'OFFLINE' }}</span></div>
    </div>
    <div class="server-card-body">@if($server->game === 'cs16' && $server->category)<span class="server-category-label">{{ ['public-1' => 'Public 1', 'public-2' => 'Public 2', 'knife-1' => 'Knife 1', 'knife-2' => 'Knife 2'][$server->category] ?? $server->category }}</span>@endif<h3>{{ $server->name }}</h3>
    <p class="muted">{{ $server->region ?: 'Mongolia' }} · {{ $server->address }}</p>
    <div class="server-metrics"><div><strong>{{ $status?->players ?? 0 }}/{{ $status?->max_players ?: $server->max_players }}</strong><small>PLAYERS</small></div><div><strong>{{ $map }}</strong><small>MAP</small></div><div><strong>{{ $status?->response_time ? $status->response_time.'ms' : '—' }}</strong><small>PING</small></div></div>
    <div class="card-actions"><a class="button button-primary" href="steam://connect/{{ $server->address }}" aria-label="Connect to {{ $server->name }}">Connect</a><a class="button button-ghost" href="{{ route('servers.show', $server) }}">Details</a></div>
    @if($status?->online && $status->player_list)
        <button class="players-button" type="button" data-player-toggle aria-label="View players on {{ $server->name }}">View players ({{ count($status->player_list) }})</button>
        <div class="player-modal" data-player-modal hidden>
            <div class="player-modal-backdrop" data-player-close></div>
            <section class="player-modal-panel" role="dialog" aria-modal="true" aria-label="{{ $server->name }} players">
                <div class="player-modal-head"><div><span class="eyebrow">LIVE PLAYERS</span><h2>{{ $server->name }}</h2><p class="muted">{{ $map }} · {{ count($status->player_list) }}/{{ $status->max_players ?: $server->max_players }}</p></div><button class="player-modal-close" type="button" data-player-close aria-label="Close players">×</button></div>
                <div class="live-player-list">@foreach($status->player_list as $player)<div class="live-player-row"><span class="player-avatar">{{ strtoupper(substr($player['name'] ?? '?', 0, 1)) }}</span><strong>{{ $player['name'] ?? 'Unknown player' }}</strong><span>{{ $player['score'] ?? '—' }} score</span><span>{{ isset($player['duration']) ? gmdate('H:i:s', max(0, (int) $player['duration'])) : '—' }}</span></div>@endforeach</div>
                <a class="button button-primary modal-connect" href="steam://connect/{{ $server->address }}">Connect to server</a>
            </section>
        </div>
    @endif
    @if($status?->created_at)<small class="updated-at">Updated {{ $status->created_at->diffForHumans() }}</small>@endif
    </div>
</article>
