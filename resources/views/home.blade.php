@extends('layouts.app')
@section('title', 'Speed.mn — Gaming Network')
@section('content')
@php
    $networkState = match (true) {
        $stats['servers'] === 0 => ['label' => 'No servers configured', 'class' => 'offline'],
        $stats['online'] === $stats['servers'] => ['label' => 'All systems operational', 'class' => 'online'],
        $stats['online'] > 0 => ['label' => 'Some servers are offline', 'class' => 'degraded'],
        default => ['label' => 'All servers are offline', 'class' => 'offline'],
    };
@endphp
@php
    $heroServer = $servers->first(fn ($server) => $server->latestStatus?->online) ?: $servers->first();
    $heroStatus = $heroServer?->latestStatus;
    $heroMap = $heroStatus?->map ?: 'de_dust2';
    $heroMapImage = $heroServer
        ? 'https://image.gametracker.com/images/maps/160x120/'.($heroServer->game === 'cs2' ? 'csgo' : 'css').'/'.$heroMap.'.jpg'
        : null;
@endphp
<section class="hero" @if($heroMapImage) style="--hero-map-image: url('{{ $heroMapImage }}')" @endif><div class="hero-backdrop"></div><div class="shell hero-grid"><div class="hero-copy-block"><p class="eyebrow">MONGOLIA'S GAMING NETWORK</p><h1>Play faster.<br><em>Play together.</em></h1><p class="hero-copy">CS2 болон CS 1.6 тоглогчдод зориулсан найдвартай серверүүд, бодит цагийн статус.</p><div class="hero-actions"><a href="{{ route('servers.index') }}" class="button button-primary">Browse servers <span>→</span></a><a href="#servers" class="text-link">View status ↓</a></div></div><div class="hero-visual"><div class="hero-glow"></div><div class="hero-status orbit-card"><span class="pulse {{ $networkState['class'] }}"></span><div><small>NETWORK STATUS</small><strong>{{ $networkState['label'] }}</strong></div><b>{{ $stats['online'] }}/{{ $stats['servers'] }}</b></div><div class="hero-stack">@foreach($servers->take(3) as $server) @php($heroStatus = $server->latestStatus) @php($heroMap = $heroStatus?->map ?: 'unknown')<a class="hero-server-card hero-server-{{ $loop->index }}" href="{{ route('servers.show', $server) }}"><img src="https://image.gametracker.com/images/maps/160x120/{{ $server->game === 'cs2' ? 'csgo' : 'css' }}/{{ $heroMap }}.jpg" alt="" loading="lazy" onerror="this.style.display='none'"><div class="hero-server-content"><span class="game-pill {{ $server->game }}">{{ $server->game === 'cs2' ? 'CS2' : 'CS 1.6' }}</span><strong>{{ $server->name }}</strong><small><i></i>{{ $heroStatus?->online ? 'LIVE' : 'OFFLINE' }} · {{ $heroStatus?->players ?? 0 }}/{{ $heroStatus?->max_players ?: $server->max_players }} players</small></div></a>@endforeach</div></div></div></section>
<section class="shell stats-row"><div><span class="stat-icon red">◈</span><strong>{{ $stats['servers'] }}</strong><small>TOTAL SERVERS</small></div><div><span class="stat-icon blue">◉</span><strong>{{ $stats['online'] }}</strong><small>ONLINE NOW</small></div><div><span class="stat-icon purple">♙</span><strong>{{ $stats['players'] }}</strong><small>PLAYERS ONLINE</small></div></section>
<section id="servers" class="shell section"><div class="section-heading"><div><p class="eyebrow">LIVE SERVERS</p><h2>Find your server</h2></div><a class="text-link" href="{{ route('servers.index') }}">View all servers →</a></div><div class="server-grid">@forelse($servers->take(6) as $server)<x-server-card :server="$server" />@empty<div class="empty">No servers configured yet. Add one from the admin panel.</div>@endforelse</div></section>
@endsection
