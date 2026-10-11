@extends('layouts.app')
@section('title', 'Сервер нэмэх хүсэлт — Speed.mn')
@section('content')
<section class="page-head"><div class="shell"><p class="eyebrow">SPEED.MN</p><h1>Сервер нэмэх хүсэлт</h1><p class="muted">Өөрийн CS2 / CS 1.6 серверийг бүртгүүлнэ үү. Админ шалгаад баталсны дараа жагсаалтад орно.</p></div></section>
<section class="shell section">
    @if(session('status'))<div class="admin-alert">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="admin-alert error"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form class="panel admin-form" method="POST" action="{{ route('submissions.store') }}">@csrf
        <div class="form-grid">
            <label>Серверийн нэр<input name="name" value="{{ old('name') }}" required maxlength="100"></label>
            <label>Тоглоом<select name="game" required>@foreach(['cs2' => 'CS2', 'cs16' => 'CS 1.6'] as $v => $l)<option value="{{ $v }}" @selected(old('game') === $v)>{{ $l }}</option>@endforeach</select></label>
            <label>CS 1.6 төрөл<select name="category"><option value="">—</option>@foreach(['public-1' => 'Public 1', 'public-2' => 'Public 2', 'knife-1' => 'Knife 1', 'knife-2' => 'Knife 2'] as $v => $l)<option value="{{ $v }}" @selected(old('category') === $v)>{{ $l }}</option>@endforeach</select></label>
            <label>IP хаяг<input name="ip" value="{{ old('ip') }}" required placeholder="203.0.113.10"></label>
            <label>Порт<input type="number" name="port" min="1" max="65535" value="{{ old('port', 27015) }}" required></label>
            <label>Бүс<input name="region" value="{{ old('region') }}" maxlength="100"></label>
            <label>Max тоглогч<input type="number" name="max_players" min="1" max="128" value="{{ old('max_players', 32) }}" required></label>
            <label>Тайлбар<input name="note" value="{{ old('note') }}" maxlength="500"></label>
        </div>
        <div class="form-actions"><button class="button button-primary" type="submit">Хүсэлт илгээх</button></div>
    </form>
    @if($submissions->isNotEmpty())
    <div class="panel"><div class="panel-heading"><h2>Миний хүсэлтүүд</h2></div>
        @foreach($submissions as $s)<p class="muted">{{ $s->name }} · {{ $s->ip }}:{{ $s->port }} · {{ ['pending' => 'Хүлээгдэж буй', 'approved' => 'Батлагдсан', 'rejected' => 'Татгалзсан'][$s->status] ?? $s->status }}</p>@endforeach
    </div>
    @endif
</section>
@endsection
