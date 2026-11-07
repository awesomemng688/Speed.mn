@extends('layouts.app')
@section('title', 'VIP / Донат — Speed.mn')
@section('description', 'Speed.mn серверүүдийг дэмжиж VIP эрх аваарай.')
@section('content')
<section class="page-head"><div class="shell"><p class="eyebrow">SPEED.MN</p><h1>VIP / Донат</h1><p class="muted">Серверүүдийг тогтвортой ажиллуулахад тусална уу. VIP эрх, үнэ, төлбөрийн мэдээллийг Discord-оор авна.</p></div></section>
<section class="shell section">
    <div class="server-grid">
        <div class="panel"><h2>VIP</h2><ul><li>Серверт давуу орох эрх</li><li>Тусгай tag</li><li>Дэмжигчдийн жагсаалт</li></ul></div>
        <div class="panel"><h2>Хэрхэн авах вэ</h2><p class="muted">Discord сервер дээр ticket нээж, Steam ID-гаа бичнэ үү. Админ төлбөрийг баталгаажуулж эрхийг олгоно.</p>
            <a class="button button-primary" href="{{ \App\Models\Setting::value('discord_url') }}" target="_blank" rel="noreferrer">Discord-д нэгдэх <span>↗</span></a></div>
    </div>
                @php
                    $vips = \App\Models\VipMember::query()->get()->filter->isActive();
                @endphp
                @if($vips->isNotEmpty())
                    <div class="panel" style="margin-top:16px"><h2>Дэмжигчид</h2><p class="muted">@foreach($vips as $v){{ $v->name }}@if(! $loop->last) · @endif @endforeach</p></div>
                @endif
</section>
@endsection
