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
        $cs2Servers = $servers->where('game', 'cs2');
        $cs16Servers = $servers->where('game', 'cs16');
    @endphp

    <section class="hero" style="--hero-map-image: url('{{ asset('img/hero/backgorund.jpg') }}')">
        <div class="hero-backdrop"></div>
        <div class="shell hero-grid">
            <div class="hero-copy-block">
                <p class="eyebrow">MONGOLIA'S GAMING NETWORK</p>
                <h1>Play faster.<br><em>Play together.</em></h1>
                <p class="hero-copy">CS2 болон CS 1.6 тоглогчдод зориулсан найдвартай серверүүд, бодит цагийн статус.</p>
                <div class="hero-actions">
                    <a href="{{ route('servers.index') }}" class="button button-primary">Browse servers <span>→</span></a>
                    <a href="#servers" class="text-link">View status ↓</a>
                </div>
            </div>

            <div class="hero-visual">
                <div class="hero-glow"></div>
                <div class="hero-status orbit-card">
                    <span class="pulse {{ $networkState['class'] }}"></span>
                    <div>
                        <small>NETWORK STATUS</small>
                        <strong>{{ $networkState['label'] }}</strong>
                    </div>
                    <b>{{ $stats['online'] }}/{{ $stats['servers'] }}</b>
                </div>

                <div class="hero-stack">
                    @foreach ($servers->take(3) as $server)
                        @php
                            $heroStatus = $server->latestStatus;
                            $heroMap = $heroStatus?->map ?: 'unknown';
                            $heroGame = $server->game === 'cs2' ? 'csgo' : 'css';
                        @endphp
                        <a class="hero-server-card hero-server-{{ $loop->index }}" href="{{ route('servers.show', $server) }}">
                            <img src="https://image.gametracker.com/images/maps/160x120/{{ $heroGame }}/{{ $heroMap }}.jpg" alt="" loading="lazy" onerror="this.style.display='none'">
                            <div class="hero-server-content">
                                <span class="game-pill {{ $server->game }}">{{ $server->game === 'cs2' ? 'CS2' : 'CS 1.6' }}</span>
                                <strong>{{ $server->name }}</strong>
                                <small><i></i>{{ $heroStatus?->online ? 'LIVE' : 'OFFLINE' }} · {{ $heroStatus?->players ?? 0 }}/{{ $heroStatus?->max_players ?: $server->max_players }} players</small>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section class="shell stats-row">
        <div><span class="stat-icon red">◈</span><strong>{{ $stats['servers'] }}</strong><small>TOTAL SERVERS</small></div>
        <div><span class="stat-icon blue">◉</span><strong>{{ $stats['online'] }}</strong><small>ONLINE NOW</small></div>
        <div><span class="stat-icon purple">♙</span><strong>{{ $stats['players'] }}</strong><small>PLAYERS ONLINE</small></div>
    </section>

    <section id="servers" class="shell section home-server-sections">
        <div class="section-heading">
            <div><p class="eyebrow">LIVE SERVERS</p><h2>Find your server</h2></div>
            <a class="text-link" href="{{ route('servers.index') }}">View all servers →</a>
        </div>

        <div class="server-category">
            <div class="server-category-heading">
                <div><span class="game-pill cs2">CS2</span><h3>Counter-Strike 2 servers</h3></div>
                <a class="text-link" href="{{ route('servers.game', 'cs2') }}">View CS2 →</a>
            </div>
            <div class="server-grid">
                @forelse ($cs2Servers as $server)
                    <x-server-card :server="$server" />
                @empty
                    <div class="empty">No CS2 servers configured yet.</div>
                @endforelse
            </div>
        </div>

        <div class="server-category">
            <div class="server-category-heading">
                <div><span class="game-pill cs16">CS 1.6</span><h3>Counter-Strike 1.6 servers</h3></div>
                <a class="text-link" href="{{ route('servers.game', 'cs16') }}">View CS 1.6 →</a>
            </div>
            <div class="server-grid">
                @forelse ($cs16Servers as $server)
                    <x-server-card :server="$server" />
                @empty
                    <div class="empty">No CS 1.6 servers configured yet.</div>
                @endforelse
            </div>
        </div>
    </section>
@endsection
