@extends('layouts.app')
@section('title', 'Model үзэх — Speed.mn')
@section('description', 'Speed.mn FastDL дахь CS 1.6 model-уудыг 3D хэлбэрээр үзэх.')
@section('content')
<section class="page-head"><div class="shell"><p class="eyebrow">MODEL</p><div class="section-heading"><h1>Model үзэх</h1></div><p class="muted">Жагсаалтаас model сонгоод хулганаар эргүүлж, дугуйгаар томруулна.</p></div></section>
<section class="shell section">
    @if(empty($groups))
        <p class="muted">Model олдсонгүй.</p>
    @else
    <div class="mdl-layout">
        <aside class="mdl-list panel">
            <input id="mdl-search" class="rank-search" type="search" placeholder="Model хайх..." autocomplete="off">
            @foreach($groups as $folder => $items)
                <details data-mdl-group @if($loop->first) open @endif>
                    <summary>{{ $folder }} <span class="muted">({{ count($items) }})</span></summary>
                    @foreach($items as $item)
                        <button type="button" class="mdl-item" data-mdl-url="{{ $item['url'] }}" data-mdl-label="{{ $folder }}/{{ $item['name'] }}">{{ $item['name'] }} <span class="muted">{{ $item['kb'] }}KB</span></button>
                    @endforeach
                </details>
            @endforeach
        </aside>
        <div>
            <div id="mdl-stage" class="mdl-stage"></div>
            <p id="mdl-status" class="muted">Зүүн талаас model сонгоно уу.</p>
        </div>
    </div>
    <script type="importmap">{"imports":{"three":"https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js","three/addons/":"https://cdn.jsdelivr.net/npm/three@0.160.0/examples/jsm/"}}</script>
    <script type="module" src="{{ asset('js/mdl-viewer.js') }}"></script>
    @endif
</section>
@endsection
