@extends('layouts.app')
@section('title', 'Бараа засах — Удирдлага')
@section('content')
<section class="page-head"><div class="shell"><p class="eyebrow">УДИРДЛАГА</p><div class="section-heading"><h1>Бараа засах</h1></div><x-admin-nav /></div></section>
<section class="shell section admin-section">
    @if($errors->any())<div class="admin-alert error" role="alert">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
    <form class="panel adm-form" method="POST" action="{{ route('admin.shop.products.update', $product) }}" enctype="multipart/form-data">@csrf @method('PUT')
        <label class="adm-field">Нэр<input name="name" value="{{ old('name', $product->name) }}" required maxlength="120"></label>
        <label class="adm-field">Ангилал<select name="category">@foreach(\App\Models\ShopProduct::CATEGORIES as $key => $label)<option value="{{ $key }}" @selected(old('category', $product->category) === $key)>{{ $label }}</option>@endforeach</select></label>
        <label class="adm-field">Үнэ (₮)<input type="number" name="price" value="{{ old('price', $product->price) }}" min="0" required></label>
        <label class="adm-field">Нөөц (хоосон = хязгааргүй)<input type="number" name="stock" value="{{ old('stock', $product->stock) }}" min="0"></label>
        <label class="adm-field">Хугацаа (хоног)<input type="number" name="duration_days" value="{{ old('duration_days', $product->duration_days) }}" min="1"></label>
        <label class="adm-field">Тайлбар<textarea name="description" rows="5" maxlength="5000">{{ old('description', $product->description) }}</textarea></label>
        <label class="adm-field">Үндсэн зураг (солих бол сонгоно)<input type="file" name="image" accept="image/jpeg,image/png,image/webp"></label>
        @if($product->imageUrl())<img src="{{ $product->imageUrl() }}" alt="" style="max-width:160px;border-radius:8px">@endif
        <label class="adm-field">Нэмэлт зураг (сонговол хуучин зургуудыг бүгдийг солино, 6 хүртэл)<input type="file" name="gallery[]" multiple accept="image/jpeg,image/png,image/webp"></label>
        <div>@foreach($product->galleryUrls() as $src)<img src="{{ $src }}" alt="" style="width:72px;height:72px;object-fit:cover;border-radius:6px;margin-right:6px">@endforeach</div>
        <label class="adm-field"><input type="checkbox" name="is_vip" value="1" @checked(old('is_vip', $product->is_vip))> VIP бараа</label>
        <button class="button button-primary" type="submit">Хадгалах</button>
        <a class="button button-ghost" href="{{ route('admin.shop.index') }}">Буцах</a>
    </form>
</section>
@endsection
