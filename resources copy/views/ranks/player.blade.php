@extends('layouts.app')
@php
    $stat = function (string $name, $fallback = 0) use ($stats) {
        foreach ($stats as $key => $value) {
            if (strcasecmp((string) $key, $name) === 0) {
                return $value;
            }
        }

        return $fallback;
    };
    $kills = (int) $stat('Kills');
    $deaths = (int) $stat('Deaths');
    $headshots = (int) $stat('Headshots');
    $shots = (int) $stat('Shots');
    $hits = (int) $stat('Hits');
    $name = $stat('Nick', $player->Player);
    $avatar = trim((string) ($player->Avatar ?? ''));
@endphp
@section('title', $name.' — '.$source['label'].' чансаа — Speed.mn')
@section('description', $name.' тоглогчийн '.$source['label'].' серверийн статистик.')
@section('content')
<section class="page-head"><div class="shell">
    <p class="eyebrow">{{ strtoupper($source['label']) }} ЧАНСАА</p>
    <h1>@if($avatar)<img src="{{ $avatar }}" alt="" width="48" height="48" style="border-radius:50%;vertical-align:middle;margin-right:12px">@endif{{ $name }}</h1>
    <p class="muted"><a class="text-link" href="{{ route('ranks.index', $category) }}">← Чансаа руу буцах</a></p>
</div></section>
<section class="shell section">
    @if($source['type'] === 'public')
    <div class="detail-metrics">
        <div><small>XP</small><strong>{{ number_format((int) $stat('XP')) }}</strong></div>
        <div><small>ТҮВШИН</small><strong>{{ (int) $stat('Level') }}</strong></div>
        <div><small>K/D</small><strong>{{ number_format($kills / max(1, $deaths), 2) }}</strong></div>
        <div><small>HEADSHOT %</small><strong>{{ number_format($headshots / max(1, $kills) * 100, 1) }}%</strong></div>
        <div><small>ЗОРИЛТ %</small><strong>{{ number_format($hits / max(1, $shots) * 100, 1) }}%</strong></div>
        <div><small>ТОГЛОСОН ЦАГ</small><strong>{{ number_format((int) $stat('Played Time') / 60, 1) }} цаг</strong></div>
    </div>
    @endif
    <div class="panel" style="margin-top:18px">
        <div class="panel-heading"><h2>Бүх статистик</h2></div>
        <div class="detail-metrics">
            @foreach($stats as $key => $value)
                @continue($value === '' || $value === null)
                <div><small>{{ strtoupper($key) }}</small><strong>{{ is_numeric($value) ? number_format((float) $value, fmod((float) $value, 1) === 0.0 ? 0 : 2) : $value }}</strong></div>
            @endforeach
        </div>
    </div>
</section>
@endsection
