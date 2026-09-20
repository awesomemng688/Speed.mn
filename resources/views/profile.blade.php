@extends('layouts.app')
@section('title', 'My profile — Speed.mn')
@section('content')
@php($user = auth()->user())
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
    <div class="profile-stats-empty">
        <div class="profile-empty-icon">◈</div>
        <div><h3>Player statistics coming soon</h3><p class="muted">Play on Speed.mn servers to build your history, playtime, and rankings.</p></div>
    </div>
</section>
@endsection
