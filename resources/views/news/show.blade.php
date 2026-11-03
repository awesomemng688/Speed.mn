@extends('layouts.app')
@section('title', $post->title.' — Speed.mn')
@section('description', $post->excerpt ?: \Illuminate\Support\Str::limit(strip_tags($post->body), 150))
@section('content')
<section class="page-head"><div class="shell"><a class="back" href="{{ route('news.index') }}">← Бүх мэдээ</a><p class="eyebrow">{{ \App\Models\Post::TYPES[$post->type] ?? $post->type }}</p><h1>{{ $post->title }}</h1><p class="muted">{{ $post->published_at?->format('Y-m-d') }}@if($post->event_at) · Тэмцээн: {{ $post->event_at->format('Y-m-d H:i') }}@endif</p></div></section>
<section class="shell section">
    <article class="panel news-body">
        @if($post->image_url)<img src="{{ $post->image_url }}" alt="">@endif
        {!! nl2br(e($post->body)) !!}
    </article>
</section>
@endsection
