@extends('layouts.app')
@section('title', 'VIP удирдлага — Speed.mn')
@section('content')
<section class="page-head"><div class="shell"><p class="eyebrow">УДИРДЛАГА</p><div class="section-heading"><h1>VIP удирдлага</h1></div><x-admin-nav /></div></section>
<section class="shell section admin-section">
    @if($errors->any())<div class="admin-alert error" role="alert">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
    <div class="adm-stats">
        <div class="panel"><small class="muted">ИДЭВХТЭЙ VIP</small><strong>{{ $activeCount }}</strong></div>
        <div class="panel"><small class="muted">НИЙТ БҮРТГЭЛ</small><strong>{{ $members->count() }}</strong></div>
        <div class="panel"><small class="muted">НИЙТ ОРЛОГО</small><strong>{{ number_format($revenue) }}₮</strong></div>
    </div>
    <form class="panel adm-form adm-inline" method="POST" action="{{ route('admin.vip.store') }}">
        @csrf
        <h2>VIP нэмэх</h2>
        <label class="adm-field">Нэр<input name="name" value="{{ old('name') }}" required maxlength="80"></label>
        <label class="adm-field">Steam ID<input name="steam_id" value="{{ old('steam_id') }}" required maxlength="40" placeholder="STEAM_0:1:12345 эсвэл 7656..."></label>
        <label class="adm-field">Багц<input name="plan" value="{{ old('plan', 'VIP') }}" required maxlength="40"></label>
        <label class="adm-field">Төлсөн дүн (₮)<input type="number" name="amount" value="{{ old('amount', 0) }}" min="0"></label>
        <label class="adm-field">Эхлэх<input type="date" name="starts_at" value="{{ old('starts_at', now()->toDateString()) }}" required></label>
        <label class="adm-field">Дуусах (хоосон = хугацаагүй)<input type="date" name="expires_at" value="{{ old('expires_at') }}"></label>
        <label class="adm-field">Тэмдэглэл<input name="note" value="{{ old('note') }}" maxlength="255"></label>
        <button class="button button-primary" type="submit">Нэмэх</button>
    </form>
    <form class="rank-search" method="GET" action="{{ route('admin.vip.index') }}" role="search">
        <input type="search" name="search" value="{{ $search }}" placeholder="Нэр, Steam ID, багц хайх" aria-label="VIP хайх">
        <button class="button button-primary" type="submit">Хайх</button>
        @if($search)<a class="text-link" href="{{ route('admin.vip.index') }}">Цэвэрлэх</a>@endif
        <a class="button button-ghost" href="{{ route('admin.vip.export', ['search' => $search ?: null]) }}">CSV татах</a>
    </form>
    <div class="panel">
        @forelse($members as $m)
            <div class="adm-row">
                <div><strong>{{ $m->name }}</strong> <span class="game-pill {{ $m->isActive() ? 'cs2' : 'cs16' }}">{{ $m->isActive() ? 'ИДЭВХТЭЙ' : 'ДУУССАН' }}</span><br><small class="muted">{{ $m->steam_id }} · {{ $m->plan }} · {{ number_format($m->amount) }}₮ · {{ $m->starts_at->format('Y-m-d') }} → {{ $m->expires_at?->format('Y-m-d') ?? 'хугацаагүй' }}{{ $m->note ? ' · '.$m->note : '' }}</small></div>
                <form method="POST" action="{{ route('admin.vip.destroy', $m) }}" onsubmit="return confirm('Устгах уу?')">@csrf @method('DELETE')<button class="button button-ghost button-small" type="submit">Устгах</button></form>
            </div>
        @empty<p class="muted">VIP бүртгэл алга.</p>@endforelse
    </div>
</section>
@endsection
