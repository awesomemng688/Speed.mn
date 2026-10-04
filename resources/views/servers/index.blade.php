@extends('layouts.app')
@section('title', ($game ? ($game === 'cs2' ? 'CS2 серверүүд' : 'CS 1.6 серверүүд') : 'Серверүүд').' — Speed.mn')
@section('description', 'Монголын CS2 болон CS 1.6 серверүүдийн бодит цагийн төлөв, газрын зураг, тоглогчдын тоог харьцуулж серверээ сонгоорой.')
@section('og_title', 'Серверүүд — Speed.mn')
@section('og_description', 'Монголын CS2 болон CS 1.6 серверүүдийг хайж, газрын зураг болон тоглогчдын тоогоор шүүнэ үү.')
@section('body_class', $game === 'cs2' ? 'servers-page cs2-page' : ($game === 'cs16' ? 'servers-page cs16-page' : 'servers-page'))
@section('content')
<section class="page-head"><div class="shell"><p class="eyebrow">SPEED.MN СҮЛЖЭЭ</p><h1>{{ $game ? ($game === 'cs2' ? 'CS2 серверүүд' : 'CS 1.6 серверүүд') : 'Бүх сервер' }}</h1><p class="muted">Серверийн төлөв, тоглогчдын тоо шинэчлэгдсэн мэдээллээр харагдана.</p><div class="tabs"><a class="{{ !$game ? 'active' : '' }}" href="{{ route('servers.index') }}">Бүгд</a><a class="{{ $game === 'cs2' ? 'active' : '' }}" href="{{ route('servers.game', 'cs2') }}">CS2</a><a class="{{ $game === 'cs16' ? 'active' : '' }}" href="{{ route('servers.game', 'cs16') }}">CS 1.6</a></div></div></section>
@php($categoryLabels = ['public-1' => 'Public 1', 'public-2' => 'Public 2', 'knife-1' => 'Knife 1', 'knife-2' => 'Knife 2'])
<section class="shell section"><form class="server-filters" method="GET" action="{{ $game ? route('servers.game', $game) : route('servers.index') }}">
    <label class="search-box"><span aria-hidden="true">⌕</span><input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Серверийн нэр эсвэл IP" autocomplete="off" aria-label="Серверийн нэр эсвэл IP хайх"></label>
    <label class="filter-field"><span>Газрын зураг</span><input type="search" name="map" value="{{ $filters['map'] ?? '' }}" placeholder="Жишээ: de_dust2" aria-label="Газрын зургаар шүүх"></label>
    <label class="filter-field"><span>Тоглогч</span><select name="players" aria-label="Тоглогчийн тоогоор шүүх"><option value="any" @selected(($filters['players'] ?? 'any') === 'any')>Бүгд</option><option value="available" @selected(($filters['players'] ?? '') === 'available')>Сул зайтай</option><option value="full" @selected(($filters['players'] ?? '') === 'full')>Дүүрэн</option></select></label>
    <label class="filter-field"><span>Эрэмбэ</span><select name="sort" aria-label="Сервер эрэмбэлэх"><option value="recommended" @selected(($filters['sort'] ?? 'recommended') === 'recommended')>Дүүрэн сервер эхэнд</option><option value="players" @selected(($filters['sort'] ?? '') === 'players')>Тоглогч олонтой</option><option value="name" @selected(($filters['sort'] ?? '') === 'name')>Нэрээр</option></select></label>
    <button class="button button-primary" type="submit">Хайх</button>
</form>
<div class="toolbar"><span class="result-count">{{ $servers->total() }} сервер</span>@if(request()->query())<a class="text-link" href="{{ $game ? route('servers.game', $game) : route('servers.index') }}">Шүүлт цэвэрлэх</a>@endif</div>
@if($game === 'cs16')
    @php($groupedServers = $servers->getCollection()->groupBy(fn ($server) => $server->category ?: 'public-1'))
    <div data-server-grid>
        @if($groupedServers->isEmpty())
            <div class="empty">Шүүлтэд тохирох сервер олдсонгүй.</div>
        @else
        @foreach($categoryLabels as $category => $label)
            @if($groupedServers->has($category))
                <div class="server-category server-category-list">
                    <div class="server-category-heading"><div><span class="game-pill cs16">{{ $label }}</span><h3>{{ $label }} сервер</h3></div></div>
                    <div class="server-grid">@foreach($groupedServers->get($category) as $server)<x-server-card :server="$server" />@endforeach</div>
                </div>
            @endif
        @endforeach
        @endif
    </div>
@else
    <div class="server-grid" data-server-grid>@forelse($servers as $server)<x-server-card :server="$server" />@empty<div class="empty">Шүүлтэд тохирох сервер олдсонгүй.</div>@endforelse</div>
@endif
<div class="pagination">{{ $servers->links() }}</div></section>
@endsection
