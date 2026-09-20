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
<section class="hero"><div class="shell hero-grid"><div><p class="eyebrow">MONGOLIA'S GAMING NETWORK</p><h1>Play faster.<br><em>Play together.</em></h1><p class="hero-copy">CS2 болон CS 1.6 тоглогчдод зориулсан найдвартай серверүүд, бодит цагийн статус.</p><div class="hero-actions"><a href="{{ route('servers.index') }}" class="button button-primary">Browse servers <span>→</span></a><a href="#servers" class="text-link">View status ↓</a></div></div><div class="hero-orbit"><div class="orbit-card"><span class="pulse {{ $networkState['class'] }}"></span><div><small>NETWORK STATUS</small><strong>{{ $networkState['label'] }}</strong></div><b>{{ $stats['online'] }}/{{ $stats['servers'] }}</b></div></div></div></section>
<section class="shell stats-row"><div><span class="stat-icon red">◈</span><strong>{{ $stats['servers'] }}</strong><small>TOTAL SERVERS</small></div><div><span class="stat-icon blue">◉</span><strong>{{ $stats['online'] }}</strong><small>ONLINE NOW</small></div><div><span class="stat-icon purple">♙</span><strong>{{ $stats['players'] }}</strong><small>PLAYERS ONLINE</small></div></section>
<section id="servers" class="shell section"><div class="section-heading"><div><p class="eyebrow">LIVE SERVERS</p><h2>Find your server</h2></div><a class="text-link" href="{{ route('servers.index') }}">View all servers →</a></div><div class="server-grid">@forelse($servers->take(6) as $server)<x-server-card :server="$server" />@empty<div class="empty">No servers configured yet. Add one from the admin panel.</div>@endforelse</div></section>
@endsection
