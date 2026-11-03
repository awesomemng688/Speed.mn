@extends('layouts.app')

@section('title', 'Speed.mn — Монголын CS2 тоглоомын сүлжээ')
@section('description', 'Монголын CS2 болон CS 1.6 серверийн бодит цагийн төлөв, тоглогчдын ранк болон Steam-д суурилсан хэрэгслүүд.')
@section('og_title', 'Speed.mn — Монголын тоглоомын сүлжээ')
@section('og_description', 'CS2 болон CS 1.6 серверийн бодит цагийн төлөв, тоглогчдын ранк, Steam профайл болон skin хэрэгслүүд.')
@section('body_class', 'home-page')
@section('jsonld')
{!! json_encode(['@'.'context' => 'https://schema.org', '@type' => 'WebSite', 'name' => 'Speed.mn', 'url' => url('/'), 'inLanguage' => 'mn', 'description' => 'Монголын CS2 болон CS 1.6 серверийн нэгдсэн сүлжээ.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}
@endsection

@section('content')
    <style>
        .nx{--nx-accent:#b6f24b;--nx-accent2:#4e8dff;--nx-line:#ffffff17;--nx-card:#0e141d;--nx-ink:#eef3fb;--nx-mute:#8fa0b8}
        .nx *{box-sizing:border-box}
        .nx-hero{position:relative;overflow:hidden;padding:84px 0 110px;isolation:isolate}
        .nx-hero:before{content:"";position:absolute;inset:0;z-index:-2;background:var(--nx-bg) center/cover no-repeat;opacity:.35;filter:saturate(.8)}
        .nx-hero:after{content:"";position:absolute;inset:0;z-index:-1;background:radial-gradient(900px 500px at 85% 10%,#4e8dff2e,transparent 60%),radial-gradient(700px 420px at 5% 100%,#b6f24b1f,transparent 60%),linear-gradient(180deg,#070a10cc,#070a10)}
        .nx-grid{display:grid;grid-template-columns:1.1fr .9fr;gap:48px;align-items:center}
        .nx-tag{display:inline-flex;align-items:center;gap:10px;padding:8px 14px;border:1px solid var(--nx-line);border-radius:999px;background:#ffffff08;font:700 .72rem/1 ui-monospace,Consolas,monospace;letter-spacing:.16em;color:var(--nx-mute)}
        .nx-hero h1{margin:22px 0 18px;font-size:clamp(2.8rem,6.4vw,5.6rem);line-height:.98;letter-spacing:-.03em;color:var(--nx-ink);text-wrap:balance}
        .nx-hero h1 em{font-style:normal;background:linear-gradient(90deg,var(--nx-accent),#7ee8c0);-webkit-background-clip:text;background-clip:text;color:transparent}
        .nx-lead{max-width:520px;font-size:1.08rem;line-height:1.75;color:#c5d0e2;margin:0 0 30px}
        .nx-cta{display:flex;flex-wrap:wrap;gap:12px;align-items:center}
        .nx-btn{display:inline-flex;align-items:center;gap:8px;padding:14px 24px;border-radius:12px;font-weight:800;text-decoration:none;border:1px solid var(--nx-line);color:var(--nx-ink);background:#ffffff0a;transition:transform .15s,background .15s}
        .nx-btn:hover{transform:translateY(-2px);background:#ffffff14}
        .nx-btn.primary{background:var(--nx-accent);border-color:var(--nx-accent);color:#0a1006;box-shadow:0 12px 34px #b6f24b33}
        .nx-btn.primary:hover{background:#c8ff5f}
        .nx-chips{display:flex;flex-wrap:wrap;gap:10px;margin-top:28px;color:var(--nx-mute);font:700 .72rem ui-monospace,Consolas,monospace;letter-spacing:.12em}
        .nx-chips span{display:inline-flex;align-items:center;gap:8px}
        .nx-chips i{width:6px;height:6px;border-radius:50%;background:var(--nx-accent)}

        .nx-console{border:1px solid var(--nx-line);border-radius:20px;background:linear-gradient(160deg,#101826f2,#0a0f18f2);box-shadow:0 30px 80px #000a;overflow:hidden}
        .nx-console-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:16px 20px;border-bottom:1px solid var(--nx-line)}
        .nx-console-head small{display:block;font:700 .68rem ui-monospace,Consolas,monospace;letter-spacing:.14em;color:var(--nx-mute)}
        .nx-console-head strong{color:var(--nx-ink)}
        .nx-console-head b{font-size:1.5rem;color:var(--nx-accent)}
        .nx-row{display:flex;align-items:center;gap:14px;padding:14px 20px;border-bottom:1px solid #ffffff0d;text-decoration:none;color:inherit;transition:background .15s}
        .nx-row:last-child{border-bottom:0}
        .nx-row:hover{background:#ffffff08}
        .nx-row img{width:64px;height:48px;border-radius:8px;object-fit:cover;background:#1a2433;flex:none}
        .nx-row-body{min-width:0;flex:1}
        .nx-row-body strong{display:block;color:var(--nx-ink);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
        .nx-row-body small{color:var(--nx-mute);font-size:.78rem}
        .nx-pill{flex:none;padding:4px 9px;border-radius:6px;font:800 .66rem ui-monospace,Consolas,monospace;letter-spacing:.08em;border:1px solid var(--nx-line);color:var(--nx-accent)}
        .nx-pill.cs16{color:#ffc857}

        .nx-ticker{border-block:1px solid var(--nx-line);background:#080c13;overflow:hidden}
        .nx-ticker-track{display:flex;gap:48px;width:max-content;padding:13px 0;animation:nx-scroll 38s linear infinite;font:700 .74rem ui-monospace,Consolas,monospace;letter-spacing:.2em;color:var(--nx-mute)}
        .nx-ticker-track span:nth-child(3n){color:var(--nx-accent)}
        @keyframes nx-scroll{to{transform:translateX(-50%)}}
        @media(prefers-reduced-motion:reduce){.nx-ticker-track{animation:none}}

        .home-page .stats-row{position:relative;z-index:3;margin-top:-48px;display:grid;grid-template-columns:repeat(3,1fr);gap:0;border:1px solid var(--nx-line);border-radius:18px;background:#0d131c;box-shadow:0 24px 60px #0008}
        .home-page .stats-row>div{padding:26px 28px;display:flex;flex-direction:column;gap:6px;border-right:1px solid var(--nx-line)}
        .home-page .stats-row>div:last-child{border-right:0}
        .home-page .stats-row strong{order:2;font-size:2.6rem;line-height:1;color:var(--nx-ink)}
        .home-page .stats-row small{order:1;font:700 .7rem ui-monospace,Consolas,monospace;letter-spacing:.16em;color:var(--nx-mute)}
        .home-page .stats-row .stat-icon{display:none}
        .home-page .live-notice{margin-top:18px}

        .nx-section{padding:70px 0 0}
        .nx-head{display:flex;flex-wrap:wrap;gap:12px;justify-content:space-between;align-items:flex-end;margin-bottom:26px}
        .nx-head p{margin:0 0 6px;font:700 .72rem ui-monospace,Consolas,monospace;letter-spacing:.18em;color:var(--nx-accent)}
        .nx-head h2{margin:0;font-size:clamp(1.8rem,3.4vw,2.6rem);letter-spacing:-.02em}
        .nx-group+.nx-group{margin-top:34px}
        .nx-group-title{display:flex;align-items:center;gap:12px;margin:0 0 16px}
        .nx-group-title h3{margin:0;font-size:1.15rem}
        .nx-group-title a{margin-left:auto}

        .nx-features{display:grid;grid-template-columns:repeat(4,1fr);gap:16px}
        .nx-feature{padding:24px;border:1px solid var(--nx-line);border-radius:16px;background:var(--nx-card)}
        .nx-feature b{display:grid;place-items:center;width:42px;height:42px;border-radius:12px;background:#b6f24b1a;color:var(--nx-accent);font-size:1.2rem;margin-bottom:16px}
        .nx-feature h3{margin:0 0 8px;font-size:1.05rem}
        .nx-feature p{margin:0;color:var(--nx-mute);line-height:1.6;font-size:.92rem}

        .nx-steps{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;counter-reset:s}
        .nx-step{position:relative;padding:26px;border:1px solid var(--nx-line);border-radius:16px;background:linear-gradient(160deg,#101826,#0b1019)}
        .nx-step:before{counter-increment:s;content:"0" counter(s);display:block;margin-bottom:14px;font:800 2rem ui-monospace,Consolas,monospace;color:#ffffff22}
        .nx-step h3{margin:0 0 8px}
        .nx-step p{margin:0;color:var(--nx-mute);line-height:1.6}

        .nx-aim{border:1px solid var(--nx-line);border-radius:18px;background:var(--nx-card);overflow:hidden}
        .nx-aim-bar{display:flex;flex-wrap:wrap;gap:14px;justify-content:space-between;align-items:center;padding:16px 20px;border-bottom:1px solid var(--nx-line);font:700 .78rem ui-monospace,Consolas,monospace;letter-spacing:.1em;color:var(--nx-mute)}
        .nx-aim-bar b{color:var(--nx-ink);font-size:1.05rem}
        .nx-aim-arena{position:relative;height:300px;cursor:crosshair;touch-action:manipulation;background:radial-gradient(circle at 50% 50%,#16233a,#0a0f18)}
        .nx-aim-target{position:absolute;width:50px;height:50px;border:0;padding:0;border-radius:50%;transform:translate(-50%,-50%);cursor:crosshair;background:radial-gradient(circle,#fff 0 12%,#ff3b30 13% 36%,#fff 37% 56%,#ff3b30 57% 100%);box-shadow:0 0 24px #ff3b3088}
        .nx-aim-hint{position:absolute;inset:auto 0 12px;margin:0;text-align:center;font:700 .7rem ui-monospace,Consolas,monospace;letter-spacing:.2em;color:var(--nx-mute);pointer-events:none}

        .nx-join{margin-top:70px;padding:44px;border:1px solid #b6f24b44;border-radius:24px;background:radial-gradient(600px 260px at 90% 0,#4e8dff26,transparent 70%),linear-gradient(160deg,#101a12,#0a0f18);display:grid;grid-template-columns:1.3fr .7fr;gap:30px;align-items:center}
        .nx-join h2{margin:0 0 12px;font-size:clamp(1.8rem,3.4vw,2.6rem);letter-spacing:-.02em}
        .nx-join p{margin:0 0 22px;color:#c5d0e2;line-height:1.7}
        .nx-join-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;text-align:center}
        .nx-join-stats div{padding:18px 8px;border:1px solid var(--nx-line);border-radius:14px;background:#ffffff08}
        .nx-join-stats strong{display:block;font-size:1.7rem;color:var(--nx-accent)}
        .nx-join-stats small{font:700 .64rem ui-monospace,Consolas,monospace;letter-spacing:.12em;color:var(--nx-mute)}
        .nx-end{height:70px}

        .nx-hot{display:flex;flex-wrap:wrap;align-items:center;gap:8px 22px;padding:22px 26px;border:1px solid #ff3b4f55;border-radius:10px;background:linear-gradient(90deg,#ff3b4f1f,#0d1219);color:var(--nx-ink);text-decoration:none;transition:transform .2s,border-color .2s}
        .nx-hot:hover{transform:translateY(-3px);border-color:#ff3b4f}
        .nx-hot-tag{font:800 .7rem ui-monospace,Consolas,monospace;letter-spacing:.16em;color:#ff4a5e}
        .nx-hot strong{font-size:1.3rem}
        .nx-hot span:not(.nx-hot-tag){color:var(--nx-mute)}
        .nx-hot b{margin-left:auto;color:#ff4a5e}
        /* v2 polish */
        .nx-hero .nx-orb{position:absolute;border-radius:50%;filter:blur(70px);z-index:-1;pointer-events:none;animation:nx-float 12s ease-in-out infinite}
        .nx-orb.a{width:380px;height:380px;right:6%;top:-60px;background:#4e8dff55}
        .nx-orb.b{width:320px;height:320px;left:-80px;bottom:-60px;background:#b6f24b3d;animation-delay:-5s}
        @keyframes nx-float{50%{transform:translate(30px,-26px) scale(1.08)}}
        .nx-hero .nx-gridlines{position:absolute;inset:0;z-index:-1;pointer-events:none;background-image:linear-gradient(#ffffff0a 1px,transparent 1px),linear-gradient(90deg,#ffffff0a 1px,transparent 1px);background-size:54px 54px;-webkit-mask-image:radial-gradient(ellipse at 50% 40%,#000 25%,transparent 75%);mask-image:radial-gradient(ellipse at 50% 40%,#000 25%,transparent 75%)}
        .nx-hero h1 em{background-size:200% 100%;animation:nx-shine 5s linear infinite;background-image:linear-gradient(90deg,#b6f24b,#7ee8c0,#4e8dff,#b6f24b)}
        @keyframes nx-shine{to{background-position:200% 0}}
        .nx-btn{position:relative;overflow:hidden}
        .nx-btn.primary:after{content:"";position:absolute;inset:0;background:linear-gradient(110deg,transparent 30%,#ffffff66 50%,transparent 70%);transform:translateX(-120%);transition:transform .6s}
        .nx-btn.primary:hover:after{transform:translateX(120%)}
        .nx-console{position:relative;backdrop-filter:blur(14px);transform:perspective(1200px) rotateY(-4deg) rotateX(2deg);transition:transform .4s}
        .nx-console:hover{transform:perspective(1200px) rotateY(0) rotateX(0)}
        .nx-console:before{content:"";position:absolute;inset:0;border-radius:inherit;padding:1px;background:linear-gradient(140deg,#b6f24b88,transparent 40%,#4e8dff88);-webkit-mask:linear-gradient(#000 0 0) content-box,linear-gradient(#000 0 0);-webkit-mask-composite:xor;mask-composite:exclude;pointer-events:none}
        .nx-row:hover{background:linear-gradient(90deg,#b6f24b14,transparent)}
        .nx-row:hover img{transform:scale(1.06)}
        .nx-row img{transition:transform .3s}
        .nx-feature,.nx-step{position:relative;transition:transform .25s,border-color .25s,box-shadow .25s}
        .nx-feature:hover,.nx-step:hover{transform:translateY(-6px);border-color:#b6f24b66;box-shadow:0 20px 50px #b6f24b14}
        .nx-feature b{transition:transform .3s,background .3s}
        .nx-feature:hover b{transform:rotate(-8deg) scale(1.1);background:#b6f24b33}
        .home-page .stats-row strong{background:linear-gradient(180deg,#fff,#9fb4d4);-webkit-background-clip:text;background-clip:text;color:transparent}
        .home-page .stats-row>div:nth-child(2) strong{background:linear-gradient(180deg,#d8ff8a,#b6f24b);-webkit-background-clip:text;background-clip:text}
        .nx-head h2{background:linear-gradient(90deg,#fff,#a9bbd6);-webkit-background-clip:text;background-clip:text;color:transparent}
        .nx-aim-target{animation:nx-pop .25s ease-out}
        @keyframes nx-pop{from{transform:translate(-50%,-50%) scale(.2);opacity:0}}
        .nx-join{position:relative;overflow:hidden}
        .nx-join:before{content:"";position:absolute;width:420px;height:420px;right:-120px;top:-160px;border-radius:50%;background:#b6f24b22;filter:blur(80px);pointer-events:none}
        .nx-rv{opacity:0;transform:translateY(26px);transition:opacity .7s,transform .7s}
        .nx-rv.in{opacity:1;transform:none}
        @media(prefers-reduced-motion:reduce){.nx-rv{opacity:1;transform:none;transition:none}.nx-orb,.nx-hero h1 em{animation:none}}
        @media(max-width:860px){.nx-console{transform:none}}
        /* v3 tidy */
        .nx{--nx-line:#ffffff12;--nx-card:#0d1219}
        .nx-hero{padding:96px 0 120px}
        .nx-hero .nx-orb{opacity:.55}
        .nx-hero .nx-gridlines{opacity:.5}
        .nx-hero h1{font-size:clamp(2.6rem,5.6vw,4.9rem);font-weight:800}
        .nx-lead{color:#a9b8cf}
        .nx-chips{display:none}
        .nx-ticker{display:none}
        .nx-console,.nx-console:hover{transform:none}
        .nx-console{border-radius:18px}
        .nx-btn{border-radius:10px;padding:13px 22px}
        .nx-section{padding:96px 0 0}
        .nx-head{margin-bottom:32px;padding-bottom:18px;border-bottom:1px solid var(--nx-line)}
        .nx-head h2{background:none;color:var(--nx-ink);-webkit-text-fill-color:currentColor}
        .nx-head p{display:flex;align-items:center;gap:10px}
        .nx-head p:before{content:"";width:22px;height:2px;background:var(--nx-accent)}
        .nx-group+.nx-group{margin-top:44px}
        .nx-group-title{padding-left:14px;border-left:3px solid var(--nx-accent)}
        .nx-feature,.nx-step{border-radius:14px;background:var(--nx-card)}
        .nx-feature:hover,.nx-step:hover{transform:translateY(-3px);box-shadow:none}
        .nx-feature b{border-radius:10px}
        .nx-steps{gap:0;border:1px solid var(--nx-line);border-radius:14px;overflow:hidden;background:var(--nx-card)}
        .nx-step{border:0;border-radius:0;background:none;border-right:1px solid var(--nx-line)}
        .nx-step:last-child{border-right:0}
        .nx-step:hover{transform:none;box-shadow:none}
        .nx-aim-arena{height:260px}
        .nx-join{margin-top:96px;border-radius:20px;border-color:var(--nx-line);background:linear-gradient(135deg,#111a14,#0a0f18)}
        .nx-end{height:96px}
        @media(max-width:860px){.nx-hero{padding:60px 0 96px}.nx-section{padding-top:64px}.nx-step{border-right:0;border-bottom:1px solid var(--nx-line)}.nx-step:last-child{border-bottom:0}.nx-join{margin-top:64px}}
        @media(max-width:1000px){.nx-features{grid-template-columns:repeat(2,1fr)}}
        @media(max-width:860px){.nx-grid,.nx-join{grid-template-columns:1fr}.nx-hero{padding:56px 0 90px}.nx-steps{grid-template-columns:1fr}.home-page .stats-row{grid-template-columns:1fr;margin-top:-34px}.home-page .stats-row>div{border-right:0;border-bottom:1px solid var(--nx-line)}.home-page .stats-row>div:last-child{border-bottom:0}.nx-join{padding:28px}}
        @media(max-width:520px){.nx-features{grid-template-columns:1fr}.nx-hero h1{font-size:2.6rem}}
    </style>

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
        $stateLabels = ['online' => 'ОНЛАЙН', 'offline' => 'ОФЛАЙН', 'stale' => 'ХУУЧИРСАН', 'unknown' => 'МЭДЭЭЛЭЛ АЛГА'];
        $tickerItems = ['CS2', 'CS 1.6', 'LIVE STATUS', 'STEAM LOGIN', 'RANK', 'SKINS', 'MONGOLIA'];
    @endphp

    <div class="nx">
        <section class="nx-hero" style="--nx-bg: url('{{ asset('img/hero/background.jpg') }}')">
            <span class="nx-orb a"></span><span class="nx-orb b"></span><span class="nx-gridlines"></span>
            @if (file_exists(public_path('video/hero.mp4')))
                <video class="nx-video" src="{{ asset('video/hero.mp4') }}" autoplay muted loop playsinline preload="none" aria-hidden="true"></video>
            @endif
            <div class="shell nx-grid">
                <div>
                    <span class="nx-tag"><span class="pulse {{ $networkState['class'] }}"></span>МОНГОЛЫН CS СҮЛЖЭЭ</span>
                    <h1>Хурдан тогло.<br><em>Хамтдаа тогло.</em></h1>
                    <p class="nx-lead">CS2 болон CS 1.6 тоглогчдод зориулсан найдвартай серверүүд, бодит цагийн статус, чансаа, skin хэрэгслүүд нэг дор.</p>
                    <div class="nx-cta">
                        <a class="nx-btn primary" href="{{ route('servers.index') }}">Сервер сонгох <span>→</span></a>
                        @auth
                            <a class="nx-btn" href="{{ route('profile') }}">Профайл</a>
                        @else
                            <a class="nx-btn" href="{{ route('steam.login', ['redirect' => '/']) }}">Steam-ээр нэвтрэх</a>
                        @endauth
                    </div>
                    <div class="nx-chips" aria-label="Network features">
                        <span><i></i>БОДИТ ЦАГИЙН ТӨЛӨВ</span>
                        <span><i></i>STEAM НЭВТРЭЛТ</span>
                        <span><i></i>CS2 + CS 1.6</span>
                    </div>
                </div>

                <div class="nx-console">
                    <div class="nx-console-head">
                        <div>
                            <small>СҮЛЖЭЭНИЙ ТӨЛӨВ · LIVE</small>
                            <strong>{{ $networkState['label'] }}</strong>
                        </div>
                        <b>{{ $stats['online'] }}/{{ $stats['servers'] }}</b>
                    </div>
                    @forelse ($servers->take(3) as $server)
                        @php
                            $heroStatus = $server->latestStatus;
                            $heroStatusState = \App\Models\ServerStatus::stateOf($heroStatus);
                            $heroIsOnline = $heroStatusState === 'online';
                            $heroMap = $heroStatus?->map ?: 'unknown';
                            $heroGame = $server->game === 'cs2' ? 'csgo' : 'css';
                        @endphp
                        <a class="nx-row" data-live-server-id="{{ $server->id }}" href="{{ route('servers.show', $server) }}">
                            <img src="https://image.gametracker.com/images/maps/160x120/{{ $heroGame }}/{{ rawurlencode($heroMap) }}.jpg" data-map-image data-fallback-src="{{ asset($server->game === 'cs2' ? 'img/hero/cs2.jfif' : 'img/hero/cs16.jpg') }}" alt="" loading="lazy">
                            <div class="nx-row-body hero-server-content">
                                <strong>{{ $server->name }}</strong>
                                <small>{{ $stateLabels[$heroStatusState] }} · {{ $heroIsOnline ? ($heroStatus?->players ?? 0) : 0 }}/{{ $heroIsOnline ? ($heroStatus?->max_players ?: $server->max_players) : $server->max_players }} тоглогч</small>
                            </div>
                            <span class="nx-pill {{ $server->game }}">{{ $server->game === 'cs2' ? 'CS2' : 'CS 1.6' }}</span>
                        </a>
                    @empty
                        <div class="nx-row"><div class="nx-row-body"><strong>Сервер бүртгэгдээгүй</strong></div></div>
                    @endforelse
                </div>
            </div>
        </section>

        <div class="nx-ticker" aria-hidden="true">
            <div class="nx-ticker-track">
                @foreach (range(1, 4) as $repeat)
                    @foreach ($tickerItems as $item)<span>{{ $item }}</span><span>/</span>@endforeach
                @endforeach
            </div>
        </div>

        <section class="shell stats-row">
            <div><span class="stat-icon">◈</span><strong data-count="{{ $stats['servers'] }}">{{ $stats['servers'] }}</strong><small>НИЙТ СЕРВЕР</small></div>
            <div><span class="stat-icon">◉</span><strong data-count="{{ $stats['online'] }}">{{ $stats['online'] }}</strong><small>ОДОО АЖИЛЛАЖ БУЙ</small></div>
            <div><span class="stat-icon">♙</span><strong data-count="{{ $stats['players'] }}">{{ $stats['players'] }}</strong><small>ТОГЛОЖ БУЙ ХҮН</small></div>
        </section>
        <section class="shell live-notice {{ $networkState['class'] }}" role="status">
            <span class="pulse {{ $networkState['class'] }}"></span>
            <strong>{{ $stats['online'] }} сервер онлайн</strong>
            <span>·</span>
            <strong>{{ $stats['players'] }} хүн тоглож байна</strong>
            <small>{{ $networkState['label'] }} · {{ $stats['stale'] }} серверийн мэдээлэл хуучирсан, {{ $stats['unknown'] }} серверийн төлөв ирээгүй</small>
        </section>

        @php
            $busiest = $servers->filter(fn ($s) => \App\Models\ServerStatus::stateOf($s->latestStatus) === 'online')->sortByDesc(fn ($s) => $s->latestStatus->players ?? 0)->first();
        @endphp
        @if ($busiest && ($busiest->latestStatus->players ?? 0) > 0)
            <section class="shell nx-section" aria-label="Хамгийн идэвхтэй сервер">
                <a class="nx-hot" href="{{ route('servers.show', $busiest) }}">
                    <span class="nx-hot-tag">🔥 ОДОО ХАМГИЙН ИДЭВХТЭЙ</span>
                    <strong>{{ $busiest->name }}</strong>
                    <span>{{ $busiest->latestStatus->players }}/{{ $busiest->latestStatus->max_players ?: $busiest->max_players }} тоглогч · {{ $busiest->latestStatus->map ?: '—' }}</span>
                    <b>Нэгдэх →</b>
                </a>
            </section>
        @endif

        <section id="servers" class="shell nx-section">
            <div class="nx-head">
                <div><p>СЕРВЕРҮҮД</p><h2>Тоглох серверээ сонго</h2></div>
                <a class="text-link" href="{{ route('servers.index') }}">Бүх сервер харах →</a>
            </div>

            <div class="nx-group">
                <div class="nx-group-title"><span class="game-pill cs2">CS2</span><h3>Counter-Strike 2</h3><a class="text-link" href="{{ route('servers.game', 'cs2') }}">Бүгдийг харах →</a></div>
                <div class="server-grid">
                    @forelse ($cs2Servers as $server)
                        <x-server-card :server="$server" />
                    @empty
                        <div class="empty">CS2 сервер одоогоор бүртгэгдээгүй байна.</div>
                    @endforelse
                </div>
            </div>

            <div class="nx-group">
                <div class="nx-group-title"><span class="game-pill cs16">CS 1.6</span><h3>Counter-Strike 1.6</h3><a class="text-link" href="{{ route('servers.game', 'cs16') }}">Бүгдийг харах →</a></div>
                <div class="server-grid">
                    @forelse ($cs16Servers as $server)
                        <x-server-card :server="$server" />
                    @empty
                        <div class="empty">CS 1.6 сервер одоогоор бүртгэгдээгүй байна.</div>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="shell nx-section">
            <div class="nx-head"><div><p>ЮУГ САНАЛ БОЛГОХ ВЭ</p><h2>Тоглогчид зориулсан бүх зүйл</h2></div></div>
            <div class="nx-features">
                <div class="nx-feature"><b>◉</b><h3>Бодит цагийн статус</h3><p>Сервер бүрийн онлайн төлөв, тоглогчийн тоо, газрын зураг 15 секунд тутамд шинэчлэгдэнэ.</p></div>
                <div class="nx-feature"><b>♜</b><h3>Чансаа</h3><p>Public болон Knife серверийн ранк, K/D, headshot хувь, тоглогчийн профайл.</p></div>
                <div class="nx-feature"><b>◈</b><h3>Skin хэрэгсэл</h3><p>Steam-ээр нэвтэрч зэвсгийн skin-ээ тохируулна.</p></div>
                <div class="nx-feature"><b>★</b><h3>VIP</h3><p>Серверүүдийг дэмжиж, давуу эрх аваарай.</p></div>
            </div>
        </section>

        <section class="shell nx-section">
            <div class="nx-head"><div><p>ЭХЛЭХ</p><h2>3 алхамаар тоглоомд орно</h2></div></div>
            <div class="nx-steps">
                <div class="nx-step"><h3>Сервер сонго</h3><p>CS2 эсвэл CS 1.6 жагсаалтаас өөрт тохирохыг нь сонгоно.</p></div>
                <div class="nx-step"><h3>Холбогдох дар</h3><p>"Холбогдох" товч Steam-ээр шууд серверт оруулна. Эсвэл IP хуулна.</p></div>
                <div class="nx-step"><h3>Чансаагаа ахиул</h3><p>Тоглож XP цуглуулаад чансааны жагсаалтад нэрээ гарга.</p></div>
            </div>
        </section>

        <section class="shell nx-section" aria-labelledby="aim-title">
            <div class="nx-head"><div><p>ДУЛААЛАХ</p><h2 id="aim-title">Aim Trainer</h2></div></div>
            <div class="nx-aim">
                <div class="nx-aim-bar">
                    <span>ОНОО <b id="aim-score">0</b></span>
                    <span>АТАЛТ <b id="aim-shots">0</b></span>
                    <span>ЗОРИЛТ <b id="aim-acc">0%</b></span>
                    <button type="button" class="nx-btn" id="aim-reset">Дахин</button>
                </div>
                <div class="nx-aim-arena" id="aim-arena">
                    <p class="nx-aim-hint" id="aim-hint">ЗОРИЛТЫГ ДАР</p>
                    <button type="button" class="nx-aim-target" id="aim-target" aria-label="Зорилт"></button>
                </div>
            </div>
        </section>

        <section class="shell nx-join" aria-labelledby="community-title">
            <div>
                <h2 id="community-title">Хамтдаа тогло. Холбоотой бай.</h2>
                <p>Speed.mn-ийн Discord-д нэгдэж серверийн мэдээ, тэмцээн, чансаа болон шинэ skin-ий мэдээлэл аваарай.</p>
                <div class="nx-cta">
                    <a class="nx-btn primary" href="https://discord.gg/93XEkJDD6" target="_blank" rel="noreferrer">Discord-д нэгдэх <span>↗</span></a>
                    <a class="nx-btn" href="{{ route('vip') }}">VIP</a>
                </div>
            </div>
            <div class="nx-join-stats">
                <div><strong>{{ $stats['online'] }}</strong><small>ОНЛАЙН СЕРВЕР</small></div>
                <div><strong>{{ $stats['players'] }}</strong><small>ТОГЛОГЧ</small></div>
                <div><strong>24/7</strong><small>СҮЛЖЭЭ</small></div>
            </div>
        </section>
        <div class="nx-end"></div>
    </div>

    <script>
    (function(){
        var rv=document.querySelectorAll('.nx-section,.nx-join,.nx-group,.nx-feature,.nx-step');
        if(!('IntersectionObserver' in window)){return;}
        rv.forEach(function(el){el.classList.add('nx-rv');});
        var io=new IntersectionObserver(function(es){es.forEach(function(e){if(e.isIntersecting){e.target.classList.add('in');io.unobserve(e.target);}});},{threshold:.08});
        rv.forEach(function(el){io.observe(el);});
    })();
    (function(){
        var arena=document.getElementById('aim-arena'),t=document.getElementById('aim-target');
        if(!arena||!t)return;
        var score=0,shots=0,els={s:document.getElementById('aim-score'),h:document.getElementById('aim-shots'),a:document.getElementById('aim-acc'),hint:document.getElementById('aim-hint')};
        function move(){var w=arena.clientWidth,h=arena.clientHeight;t.style.left=(30+Math.random()*(w-60))+'px';t.style.top=(30+Math.random()*(h-60))+'px';}
        function render(){els.s.textContent=score;els.h.textContent=shots;els.a.textContent=(shots?Math.round(score/shots*100):0)+'%';}
        arena.addEventListener('click',function(e){shots++;if(e.target===t){score++;els.hint.textContent='';move();}render();});
        document.getElementById('aim-reset').addEventListener('click',function(){score=0;shots=0;render();move();});
        move();
    })();
    </script>
@endsection
