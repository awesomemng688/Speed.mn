@extends('layouts.app')
@section('title', 'Сервер нэмэх — Speed.mn')
@section('content')
<section class="page-head"><div class="shell"><p class="eyebrow">СЕРВЕРИЙН УДИРДЛАГА</p><h1>Шинэ сервер бүртгэх</h1><p class="muted">Мэдээллийг бүртгэсний дараа серверийн холболтыг шалгаж, нийтийн жагсаалтад оруулна.</p><x-admin-nav /></div></section>
<section class="shell section admin-section admin-server-editor">@include('admin.servers.form', ['formAction' => route('admin.servers.store'), 'formMethod' => 'POST'])</section>
@endsection
