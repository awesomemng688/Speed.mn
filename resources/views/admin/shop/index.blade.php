@extends('layouts.app')
@section('title', 'Дэлгүүр — Удирдлага')
@section('content')
<section class="page-head"><div class="shell"><p class="eyebrow">УДИРДЛАГА</p><div class="section-heading"><h1>Дэлгүүр</h1></div><x-admin-nav /></div></section>
<section class="shell section admin-section">
    @if($errors->any())<div class="admin-alert error" role="alert">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
    <form class="panel adm-form" method="POST" action="{{ route('admin.shop.bank') }}">@csrf
        <h2>Төлбөрийн данс (хэрэглэгчид харагдана)</h2>
        <label class="adm-field">Банк, дансны дугаар, нэр<textarea name="shop_bank_info" rows="3" maxlength="500">{{ old('shop_bank_info', $bankInfo) }}</textarea></label>
        <button class="button button-primary" type="submit">Хадгалах</button>
    </form>
    <form class="panel adm-form adm-inline" method="POST" action="{{ route('admin.shop.products.store') }}" enctype="multipart/form-data">@csrf
        <h2>Бараа нэмэх</h2>
        <label class="adm-field">Нэр<input name="name" value="{{ old('name') }}" required maxlength="120"></label>
        <label class="adm-field">Ангилал<select name="category">@foreach(\App\Models\ShopProduct::CATEGORIES as $key => $label)<option value="{{ $key }}" @selected(old('category') === $key)>{{ $label }}</option>@endforeach</select></label>
        <label class="adm-field">Зураг (jpg, png, webp · 2MB хүртэл)<input type="file" name="image" accept="image/jpeg,image/png,image/webp"></label>
        <label class="adm-field">Үнэ (₮)<input type="number" name="price" value="{{ old('price', 0) }}" min="0" required></label>
        <label class="adm-field">Хугацаа (хоног)<input type="number" name="duration_days" value="{{ old('duration_days') }}" min="1"></label>
        <label class="adm-field">Тайлбар<input name="description" value="{{ old('description') }}" maxlength="1000"></label>
        <label class="adm-field"><input type="checkbox" name="is_vip" value="1" @checked(old('is_vip'))> VIP бараа (баталгаажихад VIP олгоно)</label>
        <button class="button button-primary" type="submit">Нэмэх</button>
    </form>
    <div class="panel"><h2>Бараа</h2>
        @forelse($products as $p)
            <div class="adm-row"><div>@if($p->imageUrl())<img src="{{ $p->imageUrl() }}" alt="" style="width:48px;height:48px;object-fit:cover;border-radius:6px;vertical-align:middle;margin-right:8px">@endif<strong>{{ $p->name }}</strong> <span class="game-pill {{ $p->active ? 'cs2' : 'cs16' }}">{{ $p->active ? 'ИДЭВХТЭЙ' : 'НУУЦ' }}</span><br><small class="muted">{{ \App\Models\ShopProduct::CATEGORIES[$p->category] ?? $p->category }} · {{ number_format($p->price) }}₮{{ $p->is_vip ? ' · VIP' : '' }}{{ $p->duration_days ? ' · '.$p->duration_days.' хоног' : '' }}</small></div>
            <div class="adm-actions">
                <form method="POST" action="{{ route('admin.shop.products.toggle', $p) }}">@csrf @method('PATCH')<button class="button button-ghost button-small" type="submit">{{ $p->active ? 'Нуух' : 'Идэвхжүүлэх' }}</button></form>
                <form method="POST" action="{{ route('admin.shop.products.destroy', $p) }}" onsubmit="return confirm('Устгах уу? Холбогдох захиалгууд мөн устна.')">@csrf @method('DELETE')<button class="button button-ghost button-small" type="submit">Устгах</button></form>
            </div></div>
        @empty<p class="muted">Бараа алга.</p>@endforelse
    </div>
    <div class="panel"><h2>Захиалгууд</h2>
        @forelse($orders as $o)
            <div class="adm-row"><div><strong>{{ $o->code }}</strong> · {{ $o->product?->name }} · {{ number_format($o->amount) }}₮<br><small class="muted">{{ $o->user?->name }} · {{ $o->user?->steam_id ?: 'Steam алга' }} · {{ $o->created_at->timezone('Asia/Ulaanbaatar')->format('Y-m-d H:i') }} · {{ \App\Models\ShopOrder::STATUSES[$o->status] ?? $o->status }}{{ $o->details ? ' · «'.$o->details.'»' : '' }}</small></div>
            @if($o->status === 'pending')
            <div class="adm-actions">
                <form method="POST" action="{{ route('admin.shop.orders.approve', $o) }}" onsubmit="return confirm('Төлбөр орсныг шалгасан уу?')">@csrf<button class="button button-primary button-small" type="submit">Баталгаажуулах</button></form>
                <form method="POST" action="{{ route('admin.shop.orders.cancel', $o) }}">@csrf<button class="button button-ghost button-small" type="submit">Цуцлах</button></form>
            </div>
            @endif</div>
        @empty<p class="muted">Захиалга алга.</p>@endforelse
    </div>
</section>
@endsection
