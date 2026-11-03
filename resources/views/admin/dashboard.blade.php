@extends('layouts.app')
@section('title', 'Dashboard — Speed.mn')
@section('content')
@php($maxPeak = max(1, collect($stats['hourly'])->max('peak') ?? 1))
<section class="page-head"><div class="shell"><p class="eyebrow">ADMINISTRATION</p><div class="section-heading"><h1>Dashboard</h1></div><p class="muted">Сүүлийн 24 цагийн нэгтгэл.</p><x-admin-nav /></div></section>
<section class="shell section admin-section">
    <div class="admin-summary">
        <div class="panel"><small>ОРГИЛ ОНЛАЙН</small><strong>{{ $stats['peak'] }}</strong>@if($stats['peak_at'])<small>{{ $stats['peak_at']->format('m-d H:i') }} (УБ)</small>@endif</div>
        <div class="panel"><small>UPTIME</small><strong>{{ $stats['uptime'] === null ? '—' : number_format($stats['uptime'], 2).'%' }}</strong><small>{{ number_format($stats['checks']) }} шалгалт</small></div>
        <div class="panel"><small>УНАСАН СЕРВЕР</small><strong>{{ $stats['down'] }}/{{ $stats['servers'] }}</strong></div>
        <div class="panel"><small>ХЭРЭГЛЭГЧ</small><strong>{{ $users }}</strong><small>{{ $disabledServers }} идэвхгүй сервер</small></div>
    </div>
    <div class="panel">
        <div class="panel-heading"><h2>Нийт тоглогч (цаг тутмын оргил)</h2></div>
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
