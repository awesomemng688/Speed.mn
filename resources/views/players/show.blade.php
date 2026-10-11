@extends('layouts.app')
@section('title', $name.' — Тоглогчийн профайл — Speed.mn')
@section('description', $name.'-ийн Speed.mn серверүүд дээрх тоглолтын статистик.')
@section('content')
<section class="page-head"><div class="shell"><a class="back" href="{{ route('ranks.index') }}">← Чансаа руу буцах</a><p class="eyebrow">SPEED.MN ТОГЛОГЧ</p><h1>{{ $name }}</h1><p class="muted">Steam ID: {{ $steamId }}</p></div></section>
<section class="shell section">
    <div class="profile-dashboard-grid">
        <div class="panel profile-progress" style="padding:24px">
            <div class="panel-heading"><div><p class="eyebrow">STEAM ПРОФАЙЛ</p><h3>{{ $name }}</h3></div></div>
            <a class="button button-ghost" href="https://steamcommunity.com/profiles/{{ $steamId }}" target="_blank" rel="noopener noreferrer">Steam профайл нээх ↗</a>
        </div>
        <div class="panel profile-network" style="padding:24px">
            <div class="panel-heading"><div><p class="eyebrow">ЧАНСААНЫ МЭДЭЭЛЭЛ</p><h3>{{ count($profiles) }} серверийн бүртгэл</h3></div></div>
            <div class="profile-server-list">
                @forelse($profiles as $profile)
                    @php($player = $profile['player'])
                    <div class="profile-server-row">
                        <strong class="profile-server-name">{{ $profile['source']['label'] }}</strong>
                        @if($profile['source']['type'] === 'public')
                            <span>XP {{ number_format((int) ($player->XP ?? $player->points ?? 0)) }}</span>
                            <span>{{ number_format((int) ($player->kills ?? 0)) }} аллага</span>
                            <span>{{ number_format((int) ($player->deaths ?? 0)) }} үхэл</span>
                        @else
                            <span>{{ number_format((int) ($player->Knife ?? 0)) }} хутганы аллага</span>
                            <span>{{ number_format((int) ($player->kills ?? 0)) }} аллага</span>
                            <span>{{ number_format((int) ($player->{'Played Time'} ?? $player->playtime ?? 0)) }} мин</span>
                        @endif
                    </div>
                @empty
                    <p class="muted">Чансааны бүртгэл олдсонгүй.</p>
                @endforelse
            </div>
        </div>
    </div>
</section>
@endsection
