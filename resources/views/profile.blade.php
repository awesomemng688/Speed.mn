@extends('layouts.app')
@section('title', 'Миний профайл — Speed.mn')
@section('description', 'Speed.mn Steam профайлаа удирдаж, тоглолтын явц болон Монголын CS2 серверийн төлөвийг хараарай.')
@section('content')
<section class="page-head profile-head"><div class="shell"><a class="back" href="{{ route('home') }}">← Нүүр хуудас</a><p class="eyebrow">ТОГЛОГЧИЙН ПРОФАЙЛ</p><h1>{{ $user->name }}</h1><p class="muted">Таны Speed.mn тоглоомын бүртгэл</p></div></section>
<section class="shell section profile-section">
    <div class="profile-layout">
        <div class="panel profile-card">
            <div class="profile-cover"></div>
            <div class="profile-main">
                @if($user->steam_avatar)
                    <img class="profile-avatar" src="{{ $user->steam_avatar }}" alt="{{ $user->name }} профайлын зураг">
                @else
                    <div class="profile-avatar profile-avatar-fallback">{{ strtoupper(substr($user->name, 0, 1)) }}</div>
                @endif
                <div class="profile-identity">
                    <div class="profile-name-row"><h2>{{ $user->name }}</h2><span class="profile-verified">STEAM ХОЛБОГДСОН</span></div>
                    <p class="muted">{{ $user->created_at?->format('Y-m') ?? 'Шинэ хэрэглэгч' }}-ээс гишүүн</p>
                </div>
                <a class="button button-ghost profile-signout" href="{{ route('steam.logout') }}">Гарах</a>
            </div>
            <div class="profile-details">
                <div><small>STEAM ID</small><strong>{{ $user->steam_id }}</strong></div>
                <div><small>БҮРТГЭЛИЙН ТӨЛӨВ</small><strong class="profile-status">Баталгаажсан</strong></div>
                <div><small>ЭРХ</small><strong>{{ $user->is_admin ? 'Админ' : 'Тоглогч' }}</strong></div>
            </div>
        </div>
        <aside class="panel profile-side">
            <p class="eyebrow">STEAM ПРОФАЙЛ</p>
            <h3>Тоглоход бэлэн үү?</h3>
            <p class="muted">Серверт холбогдоорой. Хяналтын мэдээлэл цуглахын хэрээр тоглолтын үзүүлэлтүүд энд харагдана.</p>
            <a class="button button-primary" href="{{ route('servers.index') }}">Сервер сонгох <span>→</span></a>
            <a class="profile-steam-link" href="https://steamcommunity.com/profiles/{{ $user->steam_id }}" target="_blank" rel="noreferrer">Steam профайл нээх ↗</a>
        </aside>
    </div>
    <div class="profile-dashboard-grid">
        <div class="panel profile-progress">
            <div class="panel-heading"><div><p class="eyebrow">ТОГЛОЛТЫН ЯВЦ</p><h3>{{ str_pad((string) $progress['level'], 2, '0', STR_PAD_LEFT) }} түвшин <span>{{ $progress['title'] }}</span></h3></div><strong>{{ number_format($progress['xp']) }} XP</strong></div>
            <div class="progress-track"><span style="width: {{ $progress['progress_percent'] }}%"></span></div>
            <p class="muted">@if($progress['has_data']) {{ number_format($progress['playtime']) }} минут тоглосон @if($progress['next_level_xp']) · дараагийн түвшинд {{ number_format($progress['next_level_xp'] - $progress['xp']) }} XP үлдсэн @endif @else Тоглолтын мэдээлэл алга. Public серверт тоглож явцаа эхлүүлээрэй. @endif</p>
        </div>
        <div class="panel profile-network">
            <div class="panel-heading"><div><p class="eyebrow">СҮЛЖЭЭНИЙ ТӨЛӨВ</p><h3>Одоогийн байдал</h3></div><span class="pulse online"></span></div>
            <div class="profile-network-stats"><div><strong>{{ $networkSnapshot['online_servers'] }}</strong><small>ОНЛАЙН СЕРВЕР</small></div><div><strong>{{ $networkSnapshot['players_online'] }}</strong><small>ТОГЛОГЧ</small></div></div>
        </div>
    </div>
    <div class="panel profile-activity">
        <div class="panel-heading"><div><p class="eyebrow">ОДООГИЙН ИДЭВХ</p><h3>Серверүүд</h3></div><a class="text-link" href="{{ route('servers.index') }}">Бүгдийг харах →</a></div>
        <div class="profile-server-list">
            @forelse($servers as $server)
                @php($status = $server->latestStatus)
                @php($statusState = \App\Models\ServerStatus::stateOf($status))
                @php($serverOnline = $statusState === 'online')
                <a class="profile-server-row" href="{{ route('servers.show', $server) }}"><span class="game-pill {{ $server->game }}">{{ strtoupper($server->game) }}</span><span class="profile-server-name"><strong>{{ $server->name }}</strong><small>{{ $server->address }}</small></span><span class="profile-server-players {{ $serverOnline ? 'profile-status' : '' }}">{{ $serverOnline ? ($status->players ?? 0).' тоглогч' : ['unknown' => 'Мэдээлэл алга', 'offline' => 'Офлайн', 'stale' => 'Мэдээлэл хуучирсан'][$statusState] }}</span><span>→</span></a>
            @empty
                <p class="muted">Серверийн мэдээлэл одоогоор алга.</p>
            @endforelse
        </div>
    </div>
    <div class="panel profile-activity">
        <div class="panel-heading"><div><p class="eyebrow">ТАНЫ СОНГОЛТ</p><h3>Дуртай серверүүд</h3></div><form method="GET" action="{{ route('profile') }}"><label class="filter-field"><span>Эрэмбэлэх</span><select name="favorites_sort" onchange="this.form.submit()"><option value="recent" @selected($favoriteSort === 'recent')>Шинээр хадгалсан</option><option value="name" @selected($favoriteSort === 'name')>Нэрээр</option><option value="players" @selected($favoriteSort === 'players')>Онлайн тоглогчоор</option></select></label></form><a class="text-link" href="{{ route('servers.index') }}">Сервер нэмэх →</a></div>
        <div class="profile-server-list">
            @forelse($favorites as $server)
                <div class="profile-server-row"><span class="game-pill {{ $server->game }}">{{ strtoupper($server->game) }}</span><a class="profile-server-name" href="{{ route('servers.show', $server) }}"><strong>{{ $server->name }}</strong><small>{{ $server->address }}</small></a><a class="button button-ghost" href="steam://connect/{{ $server->address }}">Холбогдох</a><form method="POST" action="{{ route('servers.unfavorite', $server) }}">@csrf @method('DELETE')<button class="favorite-remove" type="submit" aria-label="{{ $server->name }} дуртайгаас хасах">×</button></form></div>
            @empty
                <p class="muted">Одоогоор хадгалсан сервер алга.</p>
            @endforelse
        </div>
    </div>
    <div class="panel profile-activity">
        <div class="panel-heading"><div><p class="eyebrow">МЭДЭГДЭЛ</p><h3>Серверийн төлөв @if($unreadNotifications)<span class="profile-status">({{ $unreadNotifications }} уншаагүй)</span>@endif</h3></div></div>
        <div class="profile-server-list">
            @forelse($notifications as $notification)
                <div class="profile-server-row"><a class="profile-server-name" href="{{ route('servers.show', ['server' => $notification->data['server_id']]) }}"><strong>{{ $notification->data['server_name'] ?? 'Сервер' }} · {{ ($notification->data['status'] ?? '') === 'online' ? 'онлайн боллоо' : 'офлайн боллоо' }}</strong><small>{{ $notification->data['address'] ?? '' }} · {{ $notification->created_at?->diffForHumans() }}</small></a>@if(!$notification->read_at)<form method="POST" action="{{ route('notifications.read', $notification->id) }}">@csrf<button class="text-button" type="submit">Уншсан</button></form>@endif</div>
            @empty
                <p class="muted">Серверийн төлөвийн мэдэгдэл алга.</p>
            @endforelse
        </div>
    </div>
    <div class="profile-achievements">
        <div><span>◈</span><strong>Steam баталгаажсан</strong><small>Бүртгэл холбогдсон</small></div>
        <div><span>⌁</span><strong>Сүлжээний гишүүн</strong><small>{{ $user->created_at?->format('Y-m') ?? 'Шинэ' }}-ээс</small></div>
        <div class="profile-achievement-locked"><span>✦</span><strong>Эхний тоглолт</strong><small>Тоглож нээгээрэй</small></div>
    </div>
</section>
@endsection
