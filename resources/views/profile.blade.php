@extends('layouts.app')
@section('title', 'My profile — Speed.mn')
@section('content')
<section class="page-head"><div class="shell"><p class="eyebrow">PLAYER PROFILE</p><h1>{{ auth()->user()->name }}</h1><p class="muted">Steam ID: {{ auth()->user()->steam_id }}</p></div></section>
<section class="shell section"><div class="panel profile-card">@if(auth()->user()->steam_avatar)<img class="profile-avatar" src="{{ auth()->user()->steam_avatar }}" alt="">@endif<div><h2>{{ auth()->user()->name }}</h2><p class="muted">Connected with Steam</p><a class="button button-ghost" href="{{ route('steam.logout') }}">Sign out</a></div></div><div class="empty profile-empty">Player statistics will appear after monitoring data is collected.</div></section>
@endsection
