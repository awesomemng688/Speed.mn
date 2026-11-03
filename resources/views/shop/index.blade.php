@extends('layouts.app')
@section('title', 'Дэлгүүр — Speed.mn')
@section('description', 'Speed.mn VIP болон бусад бараа захиалах.')
@section('content')
<section class="page-head"><div class="shell"><p class="eyebrow">ДЭЛГҮҮР</p><div class="section-heading"><h1>VIP болон бараа</h1></div><p class="muted">Захиалга өгөөд банкны шилжүүлгээр төлнө. Админ төлбөрийг шалгаж баталгаажуулмагц идэвхжинэ.</p></div></section>
<section class="shell section admin-section">
    @if($bankInfo)<div class="panel"><h2>Төлбөр хийх данс</h2><p style="white-space:pre-line">{{ $bankInfo }}</p><small class="muted">Гүйлгээний утгад захиалгын кодыг заавал бичнэ үү.</small></div>@endif
    <div class="panel">
        @forelse($products as $p)
            <div class="adm-row">
                <div><strong>{{ $p->name }}</strong><br><small class="muted">{{ number_format($p->price) }}₮{{ $p->duration_days ? ' · '.$p->duration_days.' хоног' : '' }}{{ $p->description ? ' · '.$p->description : '' }}</small></div>
                @auth
                    <form method="POST" action="{{ route('shop.order', $p) }}">@csrf<button class="button button-primary button-small" type="submit">Захиалах</button></form>
                @else
                    <a class="button button-ghost button-small" href="{{ route('steam.login', ['redirect' => url()->current()]) }}">Нэвтэрч захиалах</a>
                @endauth
            </div>
        @empty<p class="muted">Одоогоор бараа алга.</p>@endforelse
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
