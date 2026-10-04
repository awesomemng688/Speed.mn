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
    <link rel="canonical" href="{{ url()->current() }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="@yield('og_title', 'Speed.mn Gaming Network')">
    <meta name="twitter:description" content="@yield('og_description', 'Монголын CS2 болон CS 1.6 серверийн нэгдсэн сүлжээ.')">
    <meta name="twitter:image" content="{{ asset('img/hero/background.jpg') }}">
    <title>@yield('title', 'Speed.mn Gaming Network')</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="{{ asset('css/speed.css').'?v=home-account-1' }}">
    <style>
        .server-filters{display:grid;grid-template-columns:minmax(200px,1.5fr) repeat(4,minmax(115px,1fr)) auto;align-items:end;gap:12px;padding:16px;border:1px solid var(--line);border-radius:10px;background:#11151e;margin-bottom:14px}
        .favorite-form{margin-top:10px}.favorite-button{display:inline-flex;min-height:40px;align-items:center;justify-content:center;padding:8px 12px;border:1px solid var(--line);border-radius:7px;background:#171d28;color:var(--text);font:inherit;text-decoration:none;cursor:pointer}.favorite-button[aria-pressed="true"]{color:#ffd17d;border-color:#ffd17d88}.detail-favorite{margin-top:12px}.profile-server-row form{margin:0}.favorite-remove{width:40px;height:40px;border:1px solid var(--line);border-radius:7px;background:transparent;color:var(--text);font-size:22px;cursor:pointer}
        .server-filters .search-box{display:flex;align-items:center;gap:9px;min-height:44px;padding:0 12px;border:1px solid var(--line);border-radius:7px;background:#0e121a}
        .server-filters .search-box input{width:100%;min-width:0;border:0;outline:0;background:transparent;color:var(--text);font:inherit}
        .filter-field{display:grid;gap:5px;color:var(--muted);font-size:11px}
        .filter-field input,.filter-field select{width:100%;min-height:44px;padding:9px 10px;border:1px solid var(--line);border-radius:7px;background:#0e121a;color:var(--text);font:inherit}
        .server-filters .button,.card-actions .button{min-height:44px}
        .server-card-cover img[hidden],.server-card-cover-fallback[hidden]{display:none!important}
        .server-card-overlay .status.stale{color:#ffd17d;border-color:#ffd17d55;background:#211a0dcc}
        .updated-at{display:block;margin-top:11px;color:#aeb9cb;font-size:11px}
        .updated-at time{font-variant-numeric:tabular-nums}
        .status.stale{color:#ffd17d}.status.stale i{background:#ffd17d}
        :where(a,button,input,select):focus-visible{outline:2px solid #ffd17d;outline-offset:3px}
        .nav-links a,.tabs a,.menu-toggle{min-height:44px}
        .detail-title .card-actions{align-items:center}
        @media(max-width:900px){.server-filters{grid-template-columns:repeat(2,minmax(0,1fr))}.server-filters .search-box,.server-filters>.button{grid-column:1/-1}}
        @media(max-width:640px){.server-filters{grid-template-columns:1fr;padding:12px}.server-filters .search-box,.server-filters>.button{grid-column:auto}.server-filters>.button{width:100%}.toolbar{flex-wrap:wrap}.detail-title{align-items:flex-start;flex-direction:column}.detail-title>.card-actions{width:100%}.detail-title>.card-actions .button{flex:1}.card-actions{flex-wrap:wrap;gap:8px}.card-actions .button{flex:1}.server-card-body{padding:16px}.server-card-body>p{overflow-wrap:anywhere}.hero-account-actions a,.hero-account-login{min-height:44px;display:inline-flex;align-items:center}.live-notice{align-items:flex-start;flex-wrap:wrap}.live-notice small{width:100%;margin-left:0}.rank-table-wrap{max-width:100%}}
        @media(prefers-reduced-motion:reduce){*,*::before,*::after{scroll-behavior:auto!important;animation-duration:.01ms!important;animation-iteration-count:1!important;transition-duration:.01ms!important}}
    </style>
</head>
<body class="@yield('body_class')">
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
            <a href="https://discord.gg/93XEkJDD6" target="_blank" rel="noreferrer"><span>⌁</span> Discord</a>
            @auth
                <div class="mobile-account-actions">
                    <a href="{{ route('profile') }}">Миний профайл</a>
                    <a href="{{ route('skins.bridge') }}">CS2 Skin нээх</a>
                    <a href="https://steamcommunity.com/profiles/{{ auth()->user()->steam_id }}" target="_blank" rel="noreferrer">Steam профайл ↗</a>
                    <a href="{{ route('steam.logout') }}">Гарах</a>
                </div>
            @else
                <a class="mobile-login-link" href="{{ route('steam.login', ['redirect' => url()->current()]) }}">Steam-ээр нэвтрэх</a>
            @endauth
        </nav>
        @auth
            @if(auth()->user()->is_admin)
                <a class="button button-small button-ghost" href="{{ route('admin.servers.index') }}">Admin</a>
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
                    <a href="{{ route('skins.bridge') }}">CS2 Skin нээх</a>
                    <a href="https://steamcommunity.com/profiles/{{ auth()->user()->steam_id }}" target="_blank" rel="noreferrer">Steam профайл ↗</a>
                    <a href="{{ route('steam.logout') }}">Гарах</a>
                </div>
            </div>
        @else
            <a class="button button-small button-ghost" href="{{ route('steam.login', ['redirect' => url()->current()]) }}">Steam-ээр нэвтрэх</a>
        @endauth
    </div>
</header>
<main>@if(session('error'))<div class="shell admin-alert error" role="alert">{{ session('error') }}</div>@endif @if(session('status'))<div class="shell admin-alert success" role="status">{{ session('status') }}</div>@endif @yield('content')</main>
<footer id="community"><div class="shell footer"><div><a class="brand footer-brand" href="{{ url('/') }}"><img src="{{ asset('img/hero/logo.jfif') }}" alt="Speed.mn"></a><p>Монголын CS2 болон CS 1.6 серверийн нэгдсэн сүлжээ.</p><div class="social-links"><a href="https://discord.gg/93XEkJDD6" target="_blank" rel="noreferrer">Discord ↗</a><a href="https://steamcommunity.com/" target="_blank" rel="noreferrer">Steam нийгэмлэг ↗</a><a href="mailto:contact@speed.mn">Админтай холбогдох</a></div></div><div><strong>ЦЭС</strong><a href="{{ route('servers.index') }}">Бүх сервер</a><a href="{{ route('servers.game', 'cs2') }}">CS2 сервер</a><a href="{{ route('servers.game', 'cs16') }}">CS 1.6 сервер</a><a href="{{ auth()->check() ? route('skins.bridge') : route('steam.login', ['redirect' => '/skins/bridge']) }}">Зэвсгийн skin</a><a href="{{ route('ranks.index') }}">Тоглогчдын чансаа</a><a href="#community">Серверийн дүрэм</a></div></div><div class="shell copyright">© {{ date('Y') }} Speed.mn тоглоомын сүлжээ</div></footer>
<script src="{{ asset('js/speed.js').'?v=4' }}" defer></script>
</body>
</html>
