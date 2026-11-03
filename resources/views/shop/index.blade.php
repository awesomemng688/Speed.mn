@extends('layouts.app')
@section('title', 'Дэлгүүр — Speed.mn')
@section('description', 'Speed.mn VIP болон бусад бараа захиалах.')
@section('content')
<section class="page-head"><div class="shell"><p class="eyebrow">ДЭЛГҮҮР</p><div class="section-heading"><h1>VIP болон бараа</h1></div><p class="muted">Захиалга өгөөд банкны шилжүүлгээр төлнө. Админ төлбөрийг шалгаж баталгаажуулмагц идэвхжинэ. <a href="{{ route('models.index') }}">3D model үзэх →</a></p></div></section>
<section class="shell section admin-section">
    @if($bankInfo)<div class="panel"><h2>Төлбөр хийх данс</h2><p style="white-space:pre-line">{{ $bankInfo }}</p><small class="muted">Гүйлгээний утгад захиалгын кодыг заавал бичнэ үү.</small></div>@endif
    <nav class="tabs" aria-label="Ангилал">
        <a class="{{ $category === '' ? 'active' : '' }}" href="{{ route('shop.index', ['q' => $search ?: null, 'sort' => $sort]) }}">Бүгд</a>
        @foreach(\App\Models\ShopProduct::CATEGORIES as $key => $label)<a class="{{ $category === $key ? 'active' : '' }}" href="{{ route('shop.index', ['category' => $key, 'q' => $search ?: null, 'sort' => $sort]) }}">{{ $label }}</a>@endforeach
    </nav>
    <form class="rank-search" method="GET" action="{{ route('shop.index') }}" role="search">
        @if($category)<input type="hidden" name="category" value="{{ $category }}">@endif
        <input type="search" name="q" value="{{ $search }}" placeholder="Бараа хайх" aria-label="Бараа хайх">
        <select name="sort" aria-label="Эрэмбэлэх">
            <option value="price_asc" @selected($sort === 'price_asc')>Үнэ: багаас их</option>
            <option value="price_desc" @selected($sort === 'price_desc')>Үнэ: ихээс бага</option>
            <option value="new" @selected($sort === 'new')>Шинэ эхэндээ</option>
        </select>
        <button class="button button-primary" type="submit">Хайх</button>
    </form>
    <div class="shop-grid">
        @forelse($products as $p)
            <article class="panel shop-card">
                @if($p->imageUrl())<a href="{{ route('shop.show', $p) }}"><img src="{{ $p->imageUrl() }}" alt="{{ $p->name }}" loading="lazy"></a>@endif
                <span class="game-pill {{ $p->category === 'cs16_model' ? 'cs16' : 'cs2' }}">{{ \App\Models\ShopProduct::CATEGORIES[$p->category] ?? $p->category }}</span>
                <h3><a href="{{ route('shop.show', $p) }}">{{ $p->name }}</a></h3>
                <p class="muted">{{ \Illuminate\Support\Str::limit($p->description, 100) }}</p>
                <strong>{{ number_format($p->price) }}₮{{ $p->duration_days ? ' · '.$p->duration_days.' хоног' : '' }}</strong>
                @if(! $p->inStock())
                    <span class="game-pill cs16">ДУУССАН</span>
                @elseif($p->stock !== null && $p->stock <= 5)
                    <small class="muted">Үлдэгдэл: {{ $p->stock }}</small>
                @endif
                @auth
                    @if($p->inStock())
                    <form method="POST" action="{{ route('shop.order', $p) }}">@csrf
                        <input name="details" maxlength="255" placeholder="Нэмэлт мэдээлэл (сервер, Steam нэр г.м.)" aria-label="Нэмэлт мэдээлэл">
                        <button class="button button-primary button-small" type="submit">Захиалах</button>
                    </form>
                    @endif
                @else
                    @if($p->inStock())<a class="button button-ghost button-small" href="{{ route('steam.login', ['redirect' => url()->current()]) }}">Нэвтэрч захиалах</a>@endif
                @endauth
            </article>
        @empty<div class="panel"><p class="muted">Бараа олдсонгүй.</p></div>@endforelse
    </div>
    @auth
    <div class="panel">
        <h2>Миний захиалгууд</h2>
        @forelse($orders as $o)
            <div class="adm-row">
                <div><strong>{{ $o->code }}</strong> · {{ $o->product?->name }}<br><small class="muted">{{ number_format($o->amount) }}₮ · {{ $o->created_at->timezone('Asia/Ulaanbaatar')->format('Y-m-d H:i') }}{{ $o->receipt_path ? ' · баримт хавсаргасан' : '' }}</small></div>
                <div class="adm-actions">
                    <span class="game-pill {{ $o->status === 'paid' ? 'cs2' : 'cs16' }}">{{ \App\Models\ShopOrder::STATUSES[$o->status] ?? $o->status }}</span>
                    @if($o->status === 'pending')
                        <form method="POST" action="{{ route('shop.orders.receipt', $o) }}" enctype="multipart/form-data">@csrf
                            <input type="file" name="receipt" accept="image/jpeg,image/png,image/webp" required aria-label="Төлбөрийн баримт">
                            <button class="button button-ghost button-small" type="submit">Баримт илгээх</button>
                        </form>
                        <form method="POST" action="{{ route('shop.orders.cancel', $o) }}" onsubmit="return confirm('Захиалгыг цуцлах уу?')">@csrf<button class="button button-ghost button-small" type="submit">Цуцлах</button></form>
                    @endif
                </div>
            </div>
        @empty<p class="muted">Захиалга алга.</p>@endforelse
    </div>
    @endauth
</section>
@endsection
