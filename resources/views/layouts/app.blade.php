<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="@yield('description', 'Speed.mn Gaming Network - CS2 and CS 1.6 servers')">
    <title>@yield('title', 'Speed.mn Gaming Network')</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="{{ asset('css/speed.css') }}">
</head>
<body class="@yield('body_class')">
<header class="site-header">
    <div class="shell nav">
        <a class="brand" href="{{ url('/') }}"><img src="{{ asset('img/hero/logo.jfif') }}" alt="Speed.mn"><span class="sr-only">Speed.mn</span></a>
        <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="site-menu">Menu <span>☰</span></button>
        <nav id="site-menu" class="nav-links">
            <a href="{{ route('servers.index') }}">Servers</a>
            <a href="{{ route('servers.game', 'cs2') }}">CS2</a>
            <a href="{{ route('servers.game', 'cs16') }}">CS 1.6</a>
            <a href="{{ route('ranks.index') }}">Ranks</a>
            <a href="#community">Community</a>
        </nav>
        @auth
            @if(auth()->user()->is_admin)
                <a class="button button-small button-ghost" href="{{ route('admin.servers.index') }}">Admin</a>
            @endif
            <a class="button button-small button-ghost" href="{{ route('profile') }}">Profile</a>
        @else
            <a class="button button-small button-ghost" href="{{ route('steam.login') }}">Login with Steam</a>
        @endauth
    </div>
</header>
<main>@yield('content')</main>
<footer id="community"><div class="shell footer"><div><a class="brand footer-brand" href="{{ url('/') }}"><img src="{{ asset('img/hero/logo.jfif') }}" alt="Speed.mn"></a><p>Монголын CS2 болон CS 1.6 серверийн нэгдсэн сүлжээ.</p></div><div><strong>EXPLORE</strong><a href="{{ route('servers.index') }}">All servers</a><a href="{{ route('servers.game', 'cs2') }}">CS2 servers</a><a href="{{ route('servers.game', 'cs16') }}">CS 1.6 servers</a></div></div><div class="shell copyright">© {{ date('Y') }} Speed.mn Gaming Network</div></footer>
<script src="{{ asset('js/speed.js').'?v=2' }}" defer></script>
</body>
</html>
