@extends('layouts.app')

@section('title', 'Speed.mn — Монголын CS2 тоглоомын сүлжээ')
@section('description', 'Монголын CS2 болон CS 1.6 серверийн бодит цагийн төлөв, тоглогчдын ранк болон Steam-д суурилсан хэрэгслүүд.')
@section('og_title', 'Speed.mn — Монголын тоглоомын сүлжээ')
@section('og_description', 'CS2 болон CS 1.6 серверийн бодит цагийн төлөв, тоглогчдын ранк, Steam профайл болон skin хэрэгслүүд.')
@section('body_class', 'home-page')

@section('content')
    @php
        $networkState = match (true) {
            $stats['servers'] === 0 => ['label' => 'Сервер бүртгэгдээгүй', 'class' => 'offline'],
            $stats['online'] === $stats['servers'] => ['label' => 'Бүх сервер хэвийн', 'class' => 'online'],
            $stats['stale'] > 0 && $stats['unknown'] > 0 => ['label' => 'Зарим серверийн мэдээлэл хуучирсан эсвэл алга', 'class' => 'degraded'],
            $stats['stale'] > 0 => ['label' => 'Зарим серверийн мэдээлэл хуучирсан', 'class' => 'degraded'],
            $stats['unknown'] > 0 => ['label' => 'Зарим серверийн төлөв ирээгүй', 'class' => 'degraded'],
            $stats['online'] > 0 => ['label' => 'Зарим сервер холболтгүй', 'class' => 'degraded'],
            default => ['label' => 'Бүх сервер холболтгүй', 'class' => 'offline'],
        };
        $cs2Servers = $servers->where('game', 'cs2')->take(3);
        $cs16Servers = $servers->where('game', 'cs16')->take(3);
    @endphp

    <section class="hero" style="--hero-map-image: url('{{ asset('img/hero/background.jpg') }}')">
        <div class="hero-backdrop"></div>
        <div class="shell hero-grid">
            <div class="hero-copy-block">
                <p class="eyebrow">МОНГОЛЫН ТОГЛООМЫН СҮЛЖЭЭ</p>
                <h1>Хурдан тогло.<br><em>Хамтдаа тогло.</em></h1>
                <p class="hero-copy">CS2 болон CS 1.6 тоглогчдод зориулсан найдвартай серверүүд, бодит цагийн статус.</p>
                <div class="hero-actions">
                    <a href="{{ route('servers.index') }}" class="button button-primary">Сервер сонгох <span>→</span></a>
                    <a href="#servers" class="text-link">Төлөв харах ↓</a>
                </div>
                <div class="hero-account">
                    @auth
                        @if(auth()->user()->steam_avatar)
                            <img class="hero-account-avatar" src="{{ auth()->user()->steam_avatar }}" alt="{{ auth()->user()->name }} avatar">
                        @else
                            <span class="hero-account-avatar hero-account-fallback">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                        @endif
                        <div class="hero-account-copy">
                            <small>STEAM ХОЛБОГДСОН</small>
                            <strong>{{ auth()->user()->name }}</strong>
                        </div>
                        <div class="hero-account-actions">
                            <a href="{{ route('profile') }}">Profile</a>
                            <a href="{{ route('skins.bridge') }}">Skins</a>
                        </div>
                    @else
                        <span class="hero-account-icon">◉</span>
                        <div class="hero-account-copy">
                            <small>НЭГ STEAM БҮРТГЭЛ</small>
                            <strong>Steam бүртгэлээ холбоорой</strong>
                        </div>
                        <a class="hero-account-login" href="{{ route('steam.login', ['redirect' => '/']) }}">Steam-ээр нэвтрэх <span>→</span></a>
                    @endauth
                </div>
                <div class="hero-features" aria-label="Network features">
                    <span><i></i> БОДИТ ЦАГИЙН ТӨЛӨВ</span>
                    <span><i></i> STEAM НЭВТРЭЛТ</span>
                    <span><i></i> CS2 + CS 1.6</span>
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
                            $heroStatusState = \App\Models\ServerStatus::stateOf($heroStatus);
                            $heroStatusFresh = in_array($heroStatusState, ['online', 'offline'], true);
                            $heroIsOnline = $heroStatusState === 'online';
                            $heroMap = $heroStatus?->map ?: 'unknown';
                            $heroGame = $server->game === 'cs2' ? 'csgo' : 'css';
                        @endphp
                        <a class="hero-server-card hero-server-{{ $loop->index }}" data-live-server-id="{{ $server->id }}" href="{{ route('servers.show', $server) }}">
                            <img src="https://image.gametracker.com/images/maps/160x120/{{ $heroGame }}/{{ rawurlencode($heroMap) }}.jpg" data-map-image data-fallback-src="{{ asset($server->game === 'cs2' ? 'img/hero/cs2.jfif' : 'img/hero/cs16.jpg') }}" alt="" loading="lazy">
                            <div class="hero-server-content">
                                <span class="game-pill {{ $server->game }}">{{ $server->game === 'cs2' ? 'CS2' : 'CS 1.6' }}</span>
                                <strong>{{ $server->name }}</strong>
                                <small><i></i>{{ ['online' => 'ОНЛАЙН', 'offline' => 'ОФЛАЙН', 'stale' => 'ХУУЧИРСАН', 'unknown' => 'МЭДЭЭЛЭЛ АЛГА'][$heroStatusState] }} · {{ $heroIsOnline ? ($heroStatus?->players ?? 0) : 0 }}/{{ $heroIsOnline ? ($heroStatus?->max_players ?: $server->max_players) : $server->max_players }} тоглогч</small>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section class="shell stats-row">
        <div><span class="stat-icon red">◈</span><strong>{{ $stats['servers'] }}</strong><small>НИЙТ СЕРВЕР</small></div>
        <div><span class="stat-icon blue">◉</span><strong>{{ $stats['online'] }}</strong><small>ОДОО АЖИЛЛАЖ БУЙ</small></div>
        <div><span class="stat-icon purple">♙</span><strong>{{ $stats['players'] }}</strong><small>ТОГЛОЖ БУЙ ХҮН</small></div>
    </section>
    <section class="shell live-notice {{ $networkState['class'] }}" role="status">
        <span class="pulse {{ $networkState['class'] }}"></span>
        <strong>{{ $stats['online'] }} сервер онлайн</strong>
        <span>·</span>
        <strong>{{ $stats['players'] }} хүн тоглож байна</strong>
        <small>{{ $networkState['label'] }} · {{ $stats['stale'] }} серверийн мэдээлэл хуучирсан, {{ $stats['unknown'] }} серверийн төлөв ирээгүй</small>
    </section>

    <section id="servers" class="shell section home-server-sections">
        <div class="section-heading">
            <div><p class="eyebrow">СЕРВЕРҮҮД</p><h2>Тоглох серверээ сонго</h2></div>
            <a class="text-link" href="{{ route('servers.index') }}">Бүх сервер харах →</a>
        </div>

        <div class="server-category">
            <div class="server-category-heading">
                <div><span class="game-pill cs2">CS2</span><h3>Counter-Strike 2 сервер</h3></div>
                <a class="text-link" href="{{ route('servers.game', 'cs2') }}">Бүгдийг харах →</a>
            </div>
            <div class="server-grid">
                @forelse ($cs2Servers as $server)
                    <x-server-card :server="$server" />
                @empty
                    <div class="empty">CS2 сервер одоогоор бүртгэгдээгүй байна.</div>
                @endforelse
            </div>
        </div>

        <div class="server-category">
            <div class="server-category-heading">
                <div><span class="game-pill cs16">CS 1.6</span><h3>Counter-Strike 1.6 сервер</h3></div>
                <a class="text-link" href="{{ route('servers.game', 'cs16') }}">Бүгдийг харах →</a>
            </div>
            <div class="server-grid">
                @forelse ($cs16Servers as $server)
                    <x-server-card :server="$server" />
                @empty
                    <div class="empty">CS 1.6 сервер одоогоор бүртгэгдээгүй байна.</div>
                @endforelse
            </div>
        </div>
    </section>

    <section class="shell section community-hub" aria-labelledby="community-title">
        <div class="community-panel">
            <div class="community-copy">
                <p class="eyebrow">ХАМТ ОЛОНД НЭГДЭЭРЭЙ</p>
                <h2 id="community-title">Хамтдаа тогло.<br><em>Холбоотой бай.</em></h2>
                <p>Speed.mn-ийн Discord-д нэгдэж серверийн мэдээ, тэмцээн, чансаа болон шинэ skin-ий мэдээлэл аваарай.</p>
                <div class="community-actions">
                    <a class="button button-primary" href="https://discord.gg/93XEkJDD6" target="_blank" rel="noreferrer">Discord-д нэгдэх <span>↗</span></a>
                    <a class="button button-ghost" href="https://steamcommunity.com/" target="_blank" rel="noreferrer">Steam нийгэмлэг <span>↗</span></a>
                </div>
            </div>
            <div class="community-side">
                <div class="community-orb">◈</div>
                <strong>Speed.mn хамт олон</strong>
                <span>Монголын CS2 тоглогчдын нэгдсэн орчин</span>
                <div class="community-stats">
                    <div><strong>{{ $stats['online'] }}</strong><small>ОНЛАЙН СЕРВЕР</small></div>
                    <div><strong>{{ $stats['players'] }}</strong><small>ТОГЛОГЧ</small></div>
                    <div><strong>24/7</strong><small>СҮЛЖЭЭ</small></div>
                </div>
            </div>
        </div>
    </section>
@endsection
