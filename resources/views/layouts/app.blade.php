<!doctype html>
<html lang="mn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="@yield('description', 'Монголын CS2 болон CS 1.6 серверийн нэгдсэн сүлжээ.')">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Speed.mn">
    <meta property="og:title" content="@yield('og_title', 'Speed.mn Gaming Network')">
    <meta property="og:description" content="@yield('og_description', 'Монголын CS2 болон CS 1.6 серверийн нэгдсэн сүлжээ.')">
    <meta property="og:image" content="{{ asset('img/hero/background.jpg') }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:locale" content="mn_MN">
    <link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
    <link href="https://fonts.bunny.net/css?family=rajdhani:600,700&display=swap" rel="stylesheet">
    <link rel="canonical" href="{{ url()->current() }}">
    @if(request()->routeIs('admin.*', 'profile', 'skins.*', 'two-factor.*'))
        <meta name="robots" content="noindex, nofollow">
    @endif
    @hasSection('jsonld')
        <script type="application/ld+json">@yield('jsonld')</script>
    @endif
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('og_title', 'Speed.mn Gaming Network')">
    <meta name="twitter:description" content="@yield('og_description', 'Монголын CS2 болон CS 1.6 серверийн нэгдсэн сүлжээ.')">
    <meta name="twitter:image" content="{{ asset('img/hero/background.jpg') }}">
    <title>@yield('title', 'Speed.mn Gaming Network')</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <script>try{var t=localStorage.getItem('speedmn-theme');if(t==='light')document.documentElement.setAttribute('data-theme','light');}catch(e){}</script>
    @vite(['resources/css/main.css', 'resources/js/app.js'])
</head>
<body class="@yield('body_class')">
@php
    $discordUrl = \App\Models\Setting::value('discord_url');
@endphp
@if(\App\Models\Setting::value('banner_enabled') === '1' && \App\Models\Setting::value('banner_text'))
    <div class="site-banner" role="status">
        @if(\App\Models\Setting::value('banner_url'))<a href="{{ \App\Models\Setting::value('banner_url') }}" rel="noopener">{{ \App\Models\Setting::value('banner_text') }} →</a>@else<span>{{ \App\Models\Setting::value('banner_text') }}</span>@endif
    </div>
@endif
<header class="site-header">
    <div class="shell nav">
        <a class="brand" href="{{ url('/') }}"><img src="{{ asset('img/hero/logo.jfif') }}" alt="Speed.mn"><span class="sr-only">Speed.mn</span></a>
        <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="site-menu">Цэс <span aria-hidden="true">☰</span></button>
        <nav id="site-menu" class="nav-links">
            <a class="{{ request()->routeIs('servers.index', 'servers.show') ? 'is-active' : '' }}" href="{{ route('servers.index') }}"><span>▦</span> Серверүүд</a>
            <a class="{{ request()->routeIs('servers.game') && request()->route('game') === 'cs2' ? 'is-active' : '' }}" href="{{ route('servers.game', 'cs2') }}"><span>◈</span> CS2</a>
            <a class="{{ request()->routeIs('servers.game') && request()->route('game') === 'cs16' ? 'is-active' : '' }}" href="{{ route('servers.game', 'cs16') }}"><span>◉</span> CS 1.6</a>
            <a class="{{ request()->routeIs('skins.bridge') ? 'is-active' : '' }}" href="{{ auth()->check() ? route('skins.bridge') : route('steam.login', ['redirect' => '/skins/bridge']) }}"><span>✦</span> Skin</a>
            <a class="{{ request()->routeIs('ranks.index') ? 'is-active' : '' }}" href="{{ route('ranks.index') }}"><span>♜</span> Чансаа</a>
            <a class="{{ request()->routeIs('news.*') ? 'is-active' : '' }}" href="{{ route('news.index') }}"><span>✎</span> Мэдээ</a>
            <a class="{{ request()->routeIs('vip') ? 'is-active' : '' }}" href="{{ route('vip') }}"><span>★</span> VIP</a>
            <a class="{{ request()->routeIs('models.*') ? 'is-active' : '' }}" href="{{ route('models.index') }}"><span>◈</span> MODEL</a>
            <a href="{{ $discordUrl }}" target="_blank" rel="noreferrer"><span>⌁</span> Discord</a>
            @auth
                <div class="mobile-account-actions">
                    <a href="{{ route('profile') }}">Миний профайл</a>
                    <a href="{{ route('submissions.create') }}">Сервер нэмэх</a>
                    <a href="{{ route('skins.bridge') }}">CS2 Skin нээх</a>
                    <a href="https://steamcommunity.com/profiles/{{ auth()->user()->steam_id }}" target="_blank" rel="noreferrer">Steam профайл ↗</a>
                    <a href="{{ route('steam.logout') }}">Гарах</a>
                </div>
            @else
                <a class="mobile-login-link" href="{{ route('steam.login', ['redirect' => url()->current()]) }}">Steam-ээр нэвтрэх</a>
            @endauth
        </nav>
        <button class="theme-toggle" type="button" id="theme-toggle" aria-label="Харанхуй / цайвар загвар солих">◐</button>
        @auth
            @if(auth()->user()->is_admin)
                <a class="btn btn-ghost btn-sm" href="{{ route('admin.servers.index') }}">Admin</a>
            @endif
            <div class="account-menu">
                <button class="account-trigger" type="button" aria-expanded="false" aria-controls="account-dropdown">
                    @if(auth()->user()->steam_avatar)
                        <img src="{{ auth()->user()->steam_avatar }}" alt="">
                    @else
                        <span>{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                    @endif
                    <b>{{ auth()->user()->name }}</b><i>⌄</i>
                </button>
                <div id="account-dropdown" class="account-dropdown" hidden>
                    <a href="{{ route('profile') }}">Миний профайл</a>
                    <a href="{{ route('submissions.create') }}">Сервер нэмэх</a>
                    <a href="{{ route('skins.bridge') }}">CS2 Skin нээх</a>
                    <a href="https://steamcommunity.com/profiles/{{ auth()->user()->steam_id }}" target="_blank" rel="noreferrer">Steam профайл ↗</a>
                    <a href="{{ route('steam.logout') }}">Гарах</a>
                </div>
            </div>
        @else
            <a class="btn btn-ghost btn-sm" href="{{ route('steam.login', ['redirect' => url()->current()]) }}">Steam-ээр нэвтрэх</a>
        @endauth
    </div>
</header>
<main>
    @if(session('error'))
        <div class="shell admin-alert error" role="alert">{{ session('error') }}</div>
    @endif
    @if(session('status'))
        <div class="shell admin-alert success" role="status">{{ session('status') }}</div>
    @endif
    @yield('content')
</main>
<footer id="community">
    <div class="shell footer">
        <div>
            <a class="brand footer-brand" href="{{ url('/') }}"><img src="{{ asset('img/hero/logo.jfif') }}" alt="Speed.mn"></a>
            <p>Монголын CS2 болон CS 1.6 серверийн нэгдсэн сүлжээ.</p>
            <div class="social-links">
                <a href="{{ $discordUrl }}" target="_blank" rel="noreferrer">Discord ↗</a>
                <a href="https://steamcommunity.com/" target="_blank" rel="noreferrer">Steam нийгэмлэг ↗</a>
                <a href="mailto:contact@speed.mn">Админтай холбогдох</a>
            </div>
        </div>
        <div>
            <strong>ЦЭС</strong>
            <a href="{{ route('servers.index') }}">Бүх сервер</a>
            <a href="{{ route('models.index') }}">Model үзэх</a>
            <a href="{{ route('servers.game', 'cs2') }}">CS2 сервер</a>
            <a href="{{ route('servers.game', 'cs16') }}">CS 1.6 сервер</a>
            <a href="{{ auth()->check() ? route('skins.bridge') : route('steam.login', ['redirect' => '/skins/bridge']) }}">Зэвсгийн skin</a>
            <a href="{{ route('ranks.index') }}">Тоглогчдын чансаа</a>
            <a href="#community">Серверийн дүрэм</a>
        </div>
    </div>
    <div class="shell copyright">© {{ date('Y') }} Speed.mn тоглоомын сүлжээ</div>
</footer>
<nav class="mobile-tabbar" aria-label="Үндсэн цэс">
    <a class="{{ request()->routeIs('home') || request()->is('/') ? 'is-active' : '' }}" href="{{ url('/') }}"><span aria-hidden="true">⌂</span>Нүүр</a>
    <a class="{{ request()->routeIs('servers.*') ? 'is-active' : '' }}" href="{{ route('servers.index') }}"><span aria-hidden="true">▦</span>Сервер</a>
    <a class="{{ request()->routeIs('ranks.index') ? 'is-active' : '' }}" href="{{ route('ranks.index') }}"><span aria-hidden="true">♜</span>Чансаа</a>
    <a href="{{ $discordUrl }}" target="_blank" rel="noreferrer"><span aria-hidden="true">⌁</span>Discord</a>
</nav>
<script>document.getElementById('theme-toggle').addEventListener('click',function(){var r=document.documentElement,l=r.getAttribute('data-theme')==='light';if(l){r.removeAttribute('data-theme');}else{r.setAttribute('data-theme','light');}try{localStorage.setItem('speedmn-theme',l?'dark':'light');}catch(e){}});</script>
<script>if('serviceWorker' in navigator){window.addEventListener('load',function(){navigator.serviceWorker.register('/sw.js').catch(function(){});});}</script>
@vite(['resources/js/app.js'])
@stack('scripts')
</body>
</html>