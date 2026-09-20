@extends('layouts.app')
@section('title', 'My profile — Speed.mn')
@section('content')
<section class="page-head profile-head"><div class="shell"><a class="back" href="{{ route('home') }}">← Back to network</a><p class="eyebrow">PLAYER PROFILE</p><h1>{{ $user->name }}</h1><p class="muted">Your Speed.mn gaming identity</p></div></section>
<section class="shell section profile-section">
    <div class="profile-layout">
        <div class="panel profile-card">
            <div class="profile-cover"></div>
            <div class="profile-main">
                @if($user->steam_avatar)
                    <img class="profile-avatar" src="{{ $user->steam_avatar }}" alt="{{ $user->name }} avatar">
                @else
                    <div class="profile-avatar profile-avatar-fallback">{{ strtoupper(substr($user->name, 0, 1)) }}</div>
                @endif
                <div class="profile-identity">
                    <div class="profile-name-row"><h2>{{ $user->name }}</h2><span class="profile-verified">STEAM CONNECTED</span></div>
                    <p class="muted">Member since {{ $user->created_at?->format('M Y') ?? 'recently' }}</p>
                </div>
                <a class="button button-ghost profile-signout" href="{{ route('steam.logout') }}">Sign out</a>
            </div>
            <div class="profile-details">
                <div><small>STEAM ID</small><strong>{{ $user->steam_id }}</strong></div>
                <div><small>ACCOUNT STATUS</small><strong class="profile-status">Verified</strong></div>
                <div><small>ROLE</small><strong>{{ $user->is_admin ? 'Administrator' : 'Player' }}</strong></div>
            </div>
        </div>
        <aside class="panel profile-side">
            <p class="eyebrow">STEAM PROFILE</p>
            <h3>Ready to play?</h3>
            <p class="muted">Connect to a live server and your player statistics will appear here as monitoring data is collected.</p>
            <a class="button button-primary" href="{{ route('servers.index') }}">Browse servers <span>→</span></a>
            <a class="profile-steam-link" href="https://steamcommunity.com/profiles/{{ $user->steam_id }}" target="_blank" rel="noreferrer">Open Steam profile ↗</a>
        </aside>
    </div>
    <div class="profile-dashboard-grid">
        <div class="panel profile-progress">
            <div class="panel-heading"><div><p class="eyebrow">PLAYER PROGRESS</p><h3>Level 01 <span>Rookie</span></h3></div><strong>0 XP</strong></div>
            <div class="progress-track"><span style="width: 4%"></span></div>
            <p class="muted">Your verified profile is ready. Player XP will be enabled when match history is collected.</p>
        </div>
        <div class="panel profile-network">
            <div class="panel-heading"><div><p class="eyebrow">NETWORK SNAPSHOT</p><h3>Live right now</h3></div><span class="pulse online"></span></div>
            <div class="profile-network-stats"><div><strong>{{ $networkSnapshot['online_servers'] }}</strong><small>ONLINE SERVERS</small></div><div><strong>{{ $networkSnapshot['players_online'] }}</strong><small>PLAYERS ONLINE</small></div></div>
        </div>
    </div>
    <div class="panel profile-activity">
        <div class="panel-heading"><div><p class="eyebrow">LIVE ACTIVITY</p><h3>Featured servers</h3></div><a class="text-link" href="{{ route('servers.index') }}">View all →</a></div>
        <div class="profile-server-list">
            @forelse($servers as $server)
                @php($status = $server->latestStatus)
                <a class="profile-server-row" href="{{ route('servers.show', $server) }}"><span class="game-pill {{ $server->game }}">{{ strtoupper($server->game) }}</span><span class="profile-server-name"><strong>{{ $server->name }}</strong><small>{{ $server->address }}</small></span><span class="profile-server-players {{ $status?->online ? 'profile-status' : '' }}">{{ $status?->online ? ($status->players ?? 0).' players' : 'Offline' }}</span><span>→</span></a>
            @empty
                <p class="muted">No live servers are available yet.</p>
            @endforelse
        </div>
    </div>
    <div class="profile-achievements">
        <div><span>◈</span><strong>Steam verified</strong><small>Account connected</small></div>
        <div><span>⌁</span><strong>Network member</strong><small>Joined {{ $user->created_at?->format('M Y') ?? 'recently' }}</small></div>
        <div class="profile-achievement-locked"><span>✦</span><strong>First match</strong><small>Play to unlock</small></div>
    </div>
</section>
@endsection
