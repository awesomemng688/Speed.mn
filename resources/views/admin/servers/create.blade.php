@extends('layouts.app')
@section('title', 'Add server — Speed.mn')
@section('content')
<section class="page-head"><div class="shell"><p class="eyebrow">ADMINISTRATION</p><h1>Add server</h1></div></section>
<section class="shell section">@include('admin.servers.form', ['formAction' => route('admin.servers.store'), 'formMethod' => 'POST'])</section>
@endsection
