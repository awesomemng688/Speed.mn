@extends('layouts.app')
@section('title', 'Серверийн дүрэм — Speed.mn')
@section('description', 'Speed.mn-ийн CS2 болон CS 1.6 серверүүдэд мөрдөх тоглогчдын дүрэм.')
@section('content')
<section class="page-head"><div class="shell"><a class="back" href="{{ route('home') }}">← Нүүр хуудас</a><p class="eyebrow">SPEED.MN COMMUNITY</p><h1>Серверийн дүрэм</h1><p class="muted">Бүгдэд ээлтэй, шударга тоглоомын орчныг хамтдаа бүрдүүлцгээе.</p></div></section>
<section class="shell section">
    <div class="panel" style="max-width:850px;margin:auto;padding:clamp(20px,5vw,40px)">
        <ol style="display:grid;gap:20px;padding-left:24px;line-height:1.8">
            <li><strong>Бусдыг хүндэл.</strong> Доромжлол, ялгаварлан гадуурхалт, заналхийлэл болон бусдын тоглоомыг санаатайгаар үймүүлэхийг хориглоно.</li>
            <li><strong>Шударгаар тогло.</strong> Cheat, скрипт, тоглоомын алдаа ашиглах, гуравдагч этгээдийн давуу тал хэрэглэхийг хориглоно.</li>
            <li><strong>Серверийн хэвийн ажиллагаанд саад учруулахгүй.</strong> Хуурамч санал хураалт, спам, сервер унагаах оролдлого, тоглогчдыг зориудаар саатуулахыг хориглоно.</li>
            <li><strong>Харилцааны сувгийг зөв ашигла.</strong> Сурталчилгаа, давтагдсан мессеж, бусдын хувийн мэдээллийг зөвшөөрөлгүй түгээхээс зайлсхий.</li>
            <li><strong>Админы зааврыг дага.</strong> Зөрчилтэй холбоотой асуудлыг маргалдаж хурцатгахын оронд админд тайван мэдэгд.</li>
        </ol>
        <p class="muted">Зөрчлийн шинж, давтамжаас хамааран сануулга, түр хориг эсвэл байнгын хориг тавьж болно. Дүрэм зөрчсөн тохиолдлыг <a class="text-link" href="{{ route('feedback.create') }}">санал хүсэлтээр мэдэгдээрэй</a>.</p>
    </div>
</section>
@endsection
