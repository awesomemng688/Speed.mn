@extends('layouts.app')
@section('title', 'Сервер засах — Speed.mn')
@section('content')
<section class="page-head"><div class="shell"><p class="eyebrow">СЕРВЕРИЙН УДИРДЛАГА</p><h1>Сервер засах</h1><p class="muted">{{ $server->name }} · {{ $server->address }}</p><x-admin-nav /></div></section>
<section class="shell section admin-section admin-server-editor">@include('admin.servers.form', ['formAction' => route('admin.servers.update', $server), 'formMethod' => 'PUT'])</section>
@endsection
