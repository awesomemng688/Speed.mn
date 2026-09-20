@extends('layouts.app')
@section('title', 'Servers — Speed.mn')
@section('body_class', $game === 'cs2' ? 'servers-page cs2-page' : ($game === 'cs16' ? 'servers-page cs16-page' : 'servers-page'))
@section('content')
<section class="page-head"><div class="shell"><p class="eyebrow">SPEED.MN NETWORK</p><h1>{{ $game ? ($game === 'cs2' ? 'CS2' : 'CS 1.6').' servers' : 'All servers' }}</h1><p class="muted">Live status from the latest scheduled server queries.</p><div class="tabs"><a class="{{ !$game ? 'active' : '' }}" href="{{ route('servers.index') }}">All</a><a class="{{ $game === 'cs2' ? 'active' : '' }}" href="{{ route('servers.game', 'cs2') }}">CS2</a><a class="{{ $game === 'cs16' ? 'active' : '' }}" href="{{ route('servers.game', 'cs16') }}">CS 1.6</a></div></div></section>
@php($categoryLabels = ['public-1' => 'Public 1', 'public-2' => 'Public 2', 'knife-1' => 'Knife 1', 'knife-2' => 'Knife 2'])
<section class="shell section"><div class="toolbar"><label class="search-box"><span>⌕</span><input type="search" data-server-search placeholder="Search server or IP" autocomplete="off"></label><span class="result-count" data-result-count></span></div>
@if($game === 'cs16')
    @php($groupedServers = $servers->getCollection()->groupBy(fn ($server) => $server->category ?: 'public-1'))
    <div data-server-grid>
        @forelse($categoryLabels as $category => $label)
            @if($groupedServers->has($category))
                <div class="server-category server-category-list">
                    <div class="server-category-heading"><div><span class="game-pill cs16">{{ $label }}</span><h3>{{ $label }} servers</h3></div></div>
                    <div class="server-grid">@foreach($groupedServers->get($category) as $server)<x-server-card :server="$server" />@endforeach</div>
                </div>
            @endif
        @empty
            <div class="empty">No CS 1.6 servers configured yet.</div>
        @endforelse
    </div>
@else
    <div class="server-grid" data-server-grid>@forelse($servers as $server)<x-server-card :server="$server" />@empty<div class="empty">No servers match this filter.</div>@endforelse</div>
@endif
<p class="empty is-hidden" data-no-results>No servers match your search.</p><div class="pagination">{{ $servers->links() }}</div></section>
@endsection
