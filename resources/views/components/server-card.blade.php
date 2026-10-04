@php($status = $server->latestStatus)
@php($statusState = \App\Models\ServerStatus::stateOf($status))
@php($statusIsFresh = in_array($statusState, ['online', 'offline'], true))
@php($isOnline = $statusState === 'online')
@php($map = $status?->map ?: 'unknown')
@php($mapImage = 'https://image.gametracker.com/images/maps/160x120/'.($server->game === 'cs2' ? 'csgo' : 'css').'/'.rawurlencode($map).'.jpg')
@php($fallbackImage = asset($server->game === 'cs2' ? 'img/hero/cs2.jfif' : 'img/hero/cs16.jpg'))
<article class="server-card" data-server-card data-server-id="{{ $server->id }}" data-name="{{ strtolower($server->name.' '.$server->address) }}" data-game="{{ $server->game }}">
    <div class="server-card-cover">
        <img src="{{ $mapImage }}" data-map-image data-fallback-src="{{ $fallbackImage }}" alt="{{ $map }} газрын зургийн зураг" loading="lazy">
        <div class="server-card-cover-fallback" hidden><span>{{ $server->game === 'cs2' ? 'CS2' : 'CS 1.6' }}</span><strong>{{ $map }}</strong></div>
        <div class="server-card-overlay"><span class="game-pill {{ $server->game }}">{{ $server->game === 'cs2' ? 'CS2' : 'CS 1.6' }}</span><span class="status {{ $statusState }}"><i></i>{{ ['online' => 'ОНЛАЙН', 'offline' => 'ОФЛАЙН', 'stale' => 'ХУУЧИРСАН', 'unknown' => 'МЭДЭЭЛЭЛ АЛГА'][$statusState] }}</span></div>
    </div>
    <div class="server-card-body">
        @if($server->game === 'cs16' && $server->category)<span class="server-category-label">{{ ['public-1' => 'Public 1', 'public-2' => 'Public 2', 'knife-1' => 'Knife 1', 'knife-2' => 'Knife 2'][$server->category] ?? $server->category }}</span>@endif
        <h3>{{ $server->name }}</h3>
        <p class="muted">{{ $server->region ?: 'Монгол' }} · {{ $server->address }}</p>
        <div class="server-metrics"><div><strong>{{ $isOnline ? $status->players : 0 }}/{{ $isOnline ? ($status->max_players ?: $server->max_players) : $server->max_players }}</strong><small>ТОГЛОГЧ</small></div><div><strong>{{ $isOnline ? $map : '—' }}</strong><small>ГАЗРЫН ЗУРАГ</small></div><div><strong>{{ $isOnline && $status->response_time ? $status->response_time.'мс' : '—' }}</strong><small>ХОЦРОЛТ</small></div></div>
        <div class="card-actions"><a class="button button-primary" href="steam://connect/{{ $server->address }}" aria-label="{{ $server->name }} серверт холбогдох">Холбогдох</a><button class="button button-ghost copy-address" type="button" data-copy-address="{{ $server->address }}" aria-label="{{ $server->address }} IP хуулах">IP хуулах</button><a class="button button-ghost" href="{{ route('servers.show', $server) }}">Дэлгэрэнгүй</a></div>
        @auth
            <form class="favorite-form" method="POST" action="{{ route(($server->is_favorited ?? false) ? 'servers.unfavorite' : 'servers.favorite', $server) }}">
                @csrf
                @if($server->is_favorited ?? false) @method('DELETE') @endif
                <button class="favorite-button" type="submit" aria-pressed="{{ ($server->is_favorited ?? false) ? 'true' : 'false' }}">{{ ($server->is_favorited ?? false) ? '★ Дуртайгаас хасах' : '☆ Дуртайд хадгалах' }}</button>
            </form>
        @else
            <a class="favorite-button" href="{{ route('steam.login', ['redirect' => url()->current()]) }}">☆ Дуртайд хадгалахын тулд нэвтрэх</a>
        @endauth
        @if($isOnline)
            <button class="players-button" type="button" data-player-toggle aria-label="{{ $server->name }} дээрх тоглогчдыг харах">Тоглогч харах ({{ count($status->player_list ?? []) }})</button>
            <div class="player-modal" data-player-modal hidden>
                <div class="player-modal-backdrop" data-player-close></div>
                <section class="player-modal-panel" role="dialog" aria-modal="true" aria-label="{{ $server->name }} players">
                    <div class="player-modal-head"><div><span class="eyebrow">ОДОО ТОГЛОЖ БУЙ</span><h2>{{ $server->name }}</h2><p class="muted">{{ $map }} · {{ count($status->player_list ?? []) }}/{{ $status->max_players ?: $server->max_players }}</p></div><button class="player-modal-close" type="button" data-player-close aria-label="Тоглогчдын цонх хаах">×</button></div>
                    <div class="live-player-list">@forelse($status->player_list ?? [] as $player)<div class="live-player-row"><span class="player-avatar">{{ strtoupper(substr($player['name'] ?? '?', 0, 1)) }}</span><strong>{{ $player['name'] ?? 'Тоглогч' }}</strong><span>{{ $player['score'] ?? '—' }} оноо</span><span>{{ isset($player['duration']) ? gmdate('H:i:s', max(0, (int) $player['duration'])) : '—' }}</span></div>@empty<p class="muted">Одоогоор тоглогч алга.</p>@endforelse</div>
                    <a class="button button-primary modal-connect" href="steam://connect/{{ $server->address }}">Серверт холбогдох</a>
                </section>
            </div>
        @endif
        @if($status?->created_at)<small class="updated-at">{{ $statusIsFresh ? 'Сүүлд шалгасан' : 'Мэдээлэл хуучирсан · сүүлд шалгасан' }} <time datetime="{{ $status->created_at->toIso8601String() }}">{{ $status->created_at->timezone('Asia/Ulaanbaatar')->format('Y-m-d H:i') }}</time> (УБ)</small>@else<small class="updated-at">Статусын мэдээлэл хараахан ирээгүй</small>@endif
    </div>
</article>
