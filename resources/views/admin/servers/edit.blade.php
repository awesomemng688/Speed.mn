@extends('layouts.app')
@section('title', 'Edit server — Speed.mn')
@section('content')
<section class="page-head"><div class="shell"><p class="eyebrow">ADMINISTRATION</p><h1>Edit server</h1></div></section>
<section class="shell section">@include('admin.servers.form', ['formAction' => route('admin.servers.update', $server), 'formMethod' => 'PUT'])</section>
@endsection
