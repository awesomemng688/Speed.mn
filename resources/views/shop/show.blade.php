@extends('layouts.app')
@section('title', $product->name.' — Дэлгүүр')
@section('description', \Illuminate\Support\Str::limit(strip_tags((string) $product->description), 150))
@section('content')
@php($images = array_values(array_filter(array_merge([$product->imageUrl()], $product->galleryUrls()))))
<section class="page-head"><div class="shell"><a class="back" href="{{ route('shop.index') }}">← Дэлгүүр</a><h1>{{ $product->name }}</h1></div></section>
<section class="shell section">
    <div class="shop-detail">
        <div class="shop-gallery">
            @forelse($images as $i => $src)<a href="{{ $src }}" target="_blank" rel="noopener"><img src="{{ $src }}" alt="{{ $product->name }} {{ $i + 1 }}" loading="{{ $i === 0 ? 'eager' : 'lazy' }}"></a>@empty<div class="panel"><p class="muted">Зураг алга.</p></div>@endforelse
        </div>
        <div class="panel">
            <span class="game-pill {{ $product->category === 'cs16_model' ? 'cs16' : 'cs2' }}">{{ \App\Models\ShopProduct::CATEGORIES[$product->category] ?? $product->category }}</span>
            <h2>{{ number_format($product->price) }}₮{{ $product->duration_days ? ' · '.$product->duration_days.' хоног' : '' }}</h2>
            @if(! $product->inStock())<span class="game-pill cs16">ДУУССАН</span>@elseif($product->stock !== null)<small class="muted">Үлдэгдэл: {{ $product->stock }}</small>@endif
            @if($product->description)<p style="white-space:pre-line">{{ $product->description }}</p>@endif
            @if($bankInfo)<p class="muted" style="white-space:pre-line">{{ $bankInfo }}</p>@endif
            @auth
                @if($product->inStock())
                <form method="POST" action="{{ route('shop.order', $product) }}" class="adm-form">@csrf
                    <input name="details" maxlength="255" placeholder="Нэмэлт мэдээлэл (сервер, Steam нэр г.м.)" aria-label="Нэмэлт мэдээлэл">
                    <button class="button button-primary" type="submit">Захиалах</button>
                </form>
                @endif
            @else
                @if($product->inStock())<a class="button button-ghost" href="{{ route('steam.login', ['redirect' => url()->current()]) }}">Нэвтэрч захиалах</a>@endif
            @endauth
        </div>
    </div>
</section>
@endsection
