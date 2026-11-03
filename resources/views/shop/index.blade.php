@extends('layouts.app')
@section('title', 'Дэлгүүр — Speed.mn')
@section('description', 'Speed.mn VIP болон бусад бараа захиалах.')
@section('content')
<section class="page-head"><div class="shell"><p class="eyebrow">ДЭЛГҮҮР</p><div class="section-heading"><h1>VIP болон бараа</h1></div><p class="muted">Захиалга өгөөд банкны шилжүүлгээр төлнө. Админ төлбөрийг шалгаж баталгаажуулмагц идэвхжинэ.</p></div></section>
<section class="shell section admin-section">
    @if($bankInfo)<div class="panel"><h2>Төлбөр хийх данс</h2><p style="white-space:pre-line">{{ $bankInfo }}</p><small class="muted">Гүйлгээний утгад захиалгын кодыг заавал бичнэ үү.</small></div>@endif
    <nav class="tabs" aria-label="Ангилал">
        <a class="{{ $category === '' ? 'active' : '' }}" href="{{ route('shop.index') }}">Бүгд</a>
        @foreach(\App\Models\ShopProduct::CATEGORIES as $key => $label)<a class="{{ $category === $key ? 'active' : '' }}" href="{{ route('shop.index', ['category' => $key]) }}">{{ $label }}</a>@endforeach
    </nav>
    <div class="shop-grid">
        @forelse($products as $p)
            <article class="panel shop-card">
                @if($p->imageUrl())<img src="{{ $p->imageUrl() }}" alt="{{ $p->name }}" loading="lazy">@endif
                <span class="game-pill {{ $p->category === 'cs16_model' ? 'cs16' : 'cs2' }}">{{ \App\Models\ShopProduct::CATEGORIES[$p->category] ?? $p->category }}</span>
                <h3>{{ $p->name }}</h3>
                <p class="muted">{{ $p->description }}</p>
                <strong>{{ number_format($p->price) }}₮{{ $p->duration_days ? ' · '.$p->duration_days.' хоног' : '' }}</strong>
                @auth
                    <form method="POST" action="{{ route('shop.order', $p) }}">@csrf
                        <input name="details" maxlength="255" placeholder="Нэмэлт мэдээлэл (сервер, Steam нэр г.м.)" aria-label="Нэмэлт мэдээлэл">
                        <button class="button button-primary button-small" type="submit">Захиалах</button>
                    </form>
                @else
                    <a class="button button-ghost button-small" href="{{ route('steam.login', ['redirect' => url()->current()]) }}">Нэвтэрч захиалах</a>
                @endauth
            </article>
        @empty<div class="panel"><p class="muted">Одоогоор бараа алга.</p></div>@endforelse
    </div>
    @auth
    <div class="panel">
        <h2>Миний захиалгууд</h2>
        @forelse($orders as $o)
            <div class="adm-row"><div><strong>{{ $o->code }}</strong> · {{ $o->product?->name }}<br><small class="muted">{{ number_format($o->amount) }}₮ · {{ $o->created_at->timezone('Asia/Ulaanbaatar')->format('Y-m-d H:i') }}</small></div><span class="game-pill {{ $o->status === 'paid' ? 'cs2' : 'cs16' }}">{{ \App\Models\ShopOrder::STATUSES[$o->status] ?? $o->status }}</span></div>
        @empty<p class="muted">Захиалга алга.</p>@endforelse
    </div>
    @endauth
</section>
@endsection
