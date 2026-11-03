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
            <div class="mdl-chips">
                <button type="button" class="mdl-chip is-active" data-mdl-filter="all">Бүгд</button>
                <button type="button" class="mdl-chip" data-mdl-filter="weapon">Зэвсэг</button>
                <button type="button" class="mdl-chip" data-mdl-filter="player">Тоглогч</button>
                <button type="button" class="mdl-chip" data-mdl-filter="prop">Prop</button>
                <button type="button" class="mdl-chip" data-mdl-filter="fav">★ Дуртай</button>
            </div>
            @foreach($groups as $folder => $items)
                <details data-mdl-group>
                    <summary>{{ $folder }} <span class="muted">({{ count($items) }})</span></summary>
                    @foreach($items as $item)
                        <button type="button" class="mdl-item" data-mdl-url="{{ $item['url'] }}" data-mdl-id="{{ $item['id'] }}" data-mdl-cat="{{ $item['category'] }}" data-mdl-kb="{{ $item['kb'] }}" data-mdl-label="{{ $item['id'] }}">{{ $item['name'] }} <span class="muted">{{ $item['kb'] }}KB</span></button>
                    @endforeach
                </details>
            @endforeach
        </aside>
        <div>
            <div id="mdl-stage" class="mdl-stage"></div>
            <div class="mdl-toolbar">
                <label><input id="mdl-rotate" type="checkbox" checked> Автоматаар эргэх</label>
                <label><input id="mdl-wire" type="checkbox"> Wireframe</label>
                <label>Дэвсгэр <input id="mdl-bg" type="color" value="#0b0f17"></label>
                <select id="mdl-seq" aria-label="Animation"></select>
                <button id="mdl-play" type="button" class="button button-ghost button-small">❚❚ Зогсоох</button>
                <button id="mdl-fav" type="button" class="button button-ghost button-small" disabled>☆ Дуртайд нэмэх</button>
                <a id="mdl-download" class="button button-ghost button-small" href="#" download hidden>Татах</a>
            </div>
            <p id="mdl-status" class="muted">Зүүн талаас model сонгоно уу.</p>
            <div id="mdl-info" class="mdl-info"></div>
        </div>
    </div>
    <script type="importmap">{"imports":{"three":"https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.js","three/addons/":"https://cdn.jsdelivr.net/npm/three@0.160.0/examples/jsm/"}}</script>
    <script type="module" src="{{ asset('js/mdl-viewer.js') }}"></script>
    @endif
</section>
@endsection
