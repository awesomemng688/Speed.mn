@extends('layouts.app')
@section('title', 'Хянах самбар — Speed.mn')
@section('content')
@php($maxPeak = max(1, collect($stats['hourly'])->max('peak') ?? 1))
<section class="page-head"><div class="shell"><p class="eyebrow">УДИРДЛАГА</p><div class="section-heading admin-dashboard-heading"><div><h1>Хянах самбар</h1><p class="muted">Сайтын үйл ажиллагааны сүүлийн 24 цагийн тойм.</p></div><div class="admin-hero-actions"><a class="button button-primary" href="{{ route('admin.servers.create') }}">＋ Сервер нэмэх</a><a class="button button-ghost" href="{{ route('admin.servers.index') }}">Сервер удирдах</a></div></div><x-admin-nav /></div></section>
<section class="shell section admin-section">
    @if(session('status'))<div class="admin-alert success" role="status">{{ session('status') }}</div>@endif
    <div class="adm-stats">
        <a class="panel adm-dashboard-card" href="{{ route('admin.submissions.index') }}"><small class="muted">ШИНЭ СЕРВЕРИЙН ХҮСЭЛТ</small><strong>{{ $pendingSubmissions }}</strong><span>Хүсэлт шалгах →</span></a>
        <a class="panel adm-dashboard-card" href="{{ route('admin.vip.index') }}"><small class="muted">14 ХОНОГТ ДУУСАХ VIP</small><strong>{{ $expiringVip }}</strong><span>VIP удирдах →</span></a>
        <a class="panel adm-dashboard-card" href="{{ route('admin.servers.index') }}"><small class="muted">АНХААРАХ СЕРВЕР</small><strong>{{ $attentionServers->count() }}</strong><span>Серверийн төлөв харах →</span></a>
        <a class="panel adm-dashboard-card" href="{{ route('admin.failed-jobs.index') }}"><small class="muted">АМЖИЛТГҮЙ АЖИЛ</small><strong>{{ $failedJobs }}</strong><span>Алдааг шалгах →</span></a>
    </div>
    <div class="admin-summary">
        <div class="panel"><small>ОНЛАЙНЫ ОРГИЛ</small><strong>{{ $stats['peak'] }}</strong>@if($stats['peak_at'])<small>{{ $stats['peak_at']->format('m-d H:i') }} (УБ)</small>@endif</div>
        <div class="panel"><small>СЕРВЕРИЙН НАЙДВАРТАЙ АЖИЛЛАЛТ</small><strong>{{ $stats['uptime'] === null ? '—' : number_format($stats['uptime'], 2).'%' }}</strong><small>{{ number_format($stats['checks']) }} удаагийн шалгалт</small></div>
        <div class="panel"><small>ОФЛАЙН СЕРВЕР</small><strong>{{ $stats['down'] }}/{{ $stats['servers'] }}</strong></div>
        <div class="panel"><small>ХЭРЭГЛЭГЧ</small><strong>{{ $users }}</strong><small>{{ $disabledServers }} идэвхгүй сервер</small></div>
    </div>
    <div class="panel">
        <div class="panel-heading"><h2>Анхаарах серверүүд</h2><a class="text-link" href="{{ route('admin.servers.index') }}">Бүх сервер харах →</a></div>
        @forelse($attentionServers->take(8) as $server)
            @php($state = \App\Models\ServerStatus::stateOf($server->latestStatus))
            <div class="adm-row"><div><strong>{{ $server->name }}</strong><small class="muted"> · {{ $server->address }} · {{ ['offline' => 'Офлайн', 'stale' => 'Мэдээлэл хуучирсан', 'unknown' => 'Статус алга'][$state] ?? $state }}</small></div><a class="button button-ghost button-small" href="{{ route('admin.servers.edit', $server) }}">Шалгах</a></div>
        @empty
            <p class="muted">Бүх идэвхтэй сервер хэвийн байна.</p>
        @endforelse
    </div>
    <div class="panel">
        <div class="panel-heading"><h2>Нийт тоглогч — цагийн оргил</h2></div>
        <div class="history">
            @forelse($stats['hourly'] as $point)
                <div class="bar-online" style="height:{{ max(8, (int) round($point['peak'] / $maxPeak * 100)) }}%" title="{{ $point['label'] }} · {{ $point['peak'] }} тоглогч"></div>
            @empty
                <p class="muted">Мэдээлэл алга.</p>
            @endforelse
        </div>
    </div>
</section>
@endsection
