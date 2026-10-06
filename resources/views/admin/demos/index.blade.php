@extends('layouts.app')
@section('title', 'Demo videos — Speed.mn')
@section('content')
<section class="page-head"><div class="shell"><p class="eyebrow">ADMINISTRATION</p><h1>Demo videos</h1><p class="muted">CS2 MP4 бичлэгүүдийг private сангаас үзнэ.</p><x-admin-nav /></div></section>
<section class="shell section admin-section">
    @if(session('status'))<div class="admin-alert success" role="status">{{ session('status') }}</div>@endif
    <div class="panel admin-form-panel">
        <h2>MP4 нэмэх</h2>
        <form class="admin-form" method="POST" action="{{ route('admin.demos.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="form-grid">
                <label>Тоглолтын нэр<input name="title" value="{{ old('title') }}" maxlength="180" required placeholder="Team A vs Team B"></label>
                <label>Map<input name="map" value="{{ old('map') }}" maxlength="64" placeholder="de_dust2"></label>
                <label>Тоглосон огноо<input name="recorded_at" type="datetime-local" value="{{ old('recorded_at') }}"></label>
                <label>MP4 файл<input name="video" type="file" accept="video/mp4,.mp4" required><small>Зөвхөн MP4, дээд хэмжээ 1 GB. Raw .dem шууд тоглохгүй.</small></label>
            </div>
            @error('video')<p class="field-error">{{ $message }}</p>@enderror
            @error('title')<p class="field-error">{{ $message }}</p>@enderror
            @error('map')<p class="field-error">{{ $message }}</p>@enderror
            @error('recorded_at')<p class="field-error">{{ $message }}</p>@enderror
            <button class="button button-primary" type="submit">MP4 хадгалах</button>
        </form>
    </div>
    <div class="demo-video-list">
        @forelse($demos as $demo)
            <article class="panel demo-video-item">
                <div class="panel-heading"><div><h2>{{ $demo->title }}</h2><small class="muted">{{ $demo->map ?: 'Map тодорхойгүй' }} · {{ $demo->recorded_at?->format('Y-m-d H:i') ?: 'Огноо тодорхойгүй' }} · {{ $demo->uploadedBy?->name ?: 'Админ' }}</small></div>
                    <form method="POST" action="{{ route('admin.demos.destroy', $demo) }}" onsubmit="return confirm('Энэ demo-г устгах уу?')">@csrf @method('DELETE')<button class="text-button danger" type="submit">Устгах</button></form>
                </div>
                <video controls preload="metadata" playsinline src="{{ route('admin.demos.stream', $demo) }}">Таны browser MP4 тоглуулах боломжгүй байна.</video>
            </article>
        @empty
            <p class="empty">Одоогоор MP4 demo алга.</p>
        @endforelse
    </div>
    {{ $demos->links() }}
</section>
<style>
    .admin-form-panel{padding:22px;margin-bottom:18px}.admin-form-panel h2{margin:0 0 16px;font-size:18px}.demo-video-list{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,420px),1fr));gap:14px}.demo-video-item{padding:16px;min-width:0}.demo-video-item .panel-heading{align-items:flex-start;gap:12px}.demo-video-item h2{margin:0;font-size:16px}.demo-video-item video{display:block;width:100%;max-height:440px;margin-top:14px;border-radius:8px;background:#05070b}.field-error{color:#ff8998;font-size:12px}
</style>
@endsection