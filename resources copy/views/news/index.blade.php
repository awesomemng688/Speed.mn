@extends('layouts.app')
@section('title', 'Мэдээ / Тэмцээн — Speed.mn')
@section('description', 'Speed.mn-ийн мэдээ, тэмцээн болон шинэчлэлтүүд.')
@section('content')
<section class="page-head"><div class="shell"><p class="eyebrow">SPEED.MN</p><h1>Мэдээ / Тэмцээн</h1></div></section>
<section class="shell section">
    <div class="server-grid">
        @forelse($posts as $post)
            <a class="panel news-card" href="{{ route('news.show', $post) }}">
                @if($post->image_url)<img src="{{ $post->image_url }}" alt="" loading="lazy">@endif
                <span class="game-pill {{ $post->type === 'tournament' ? 'cs2' : 'cs16' }}">{{ \App\Models\Post::TYPES[$post->type] ?? $post->type }}</span>
                <h3>{{ $post->title }}</h3>
                <p class="muted">{{ $post->excerpt ?: \Illuminate\Support\Str::limit(strip_tags($post->body), 140) }}</p>
                <small class="muted">{{ $post->published_at?->format('Y-m-d') }}@if($post->event_at) · Тэмцээн: {{ $post->event_at->format('Y-m-d H:i') }}@endif</small>
            </a>
        @empty
            <div class="empty">Одоогоор нийтлэл алга.</div>
        @endforelse
    </div>
    {{ $posts->links() }}
</section>
@endsection
