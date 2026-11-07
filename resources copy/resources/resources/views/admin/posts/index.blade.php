@extends('layouts.app')
@section('title', 'Мэдээ / Тэмцээн — Удирдлага')
@section('content')
<section class="page-head"><div class="shell"><p class="eyebrow">УДИРДЛАГА</p><div class="section-heading"><h1>Мэдээ / Тэмцээн</h1></div><x-admin-nav /></div></section>
<section class="shell section admin-section">
    <p><a class="button button-primary" href="{{ route('admin.posts.create') }}">+ Шинэ нийтлэл</a></p>
    <form class="rank-search" method="GET" action="{{ route('admin.posts.index') }}" role="search">
        <input type="search" name="search" value="{{ $search }}" placeholder="Гарчгаар хайх" aria-label="Нийтлэл хайх">
        <button class="button button-primary" type="submit">Хайх</button>
        @if($search)<a class="text-link" href="{{ route('admin.posts.index') }}">Цэвэрлэх</a>@endif
    </form>
    <div class="panel">
        @forelse($posts as $post)
            <div class="adm-row">
                <div><strong>{{ $post->title }}</strong><br><small class="muted">{{ \App\Models\Post::TYPES[$post->type] ?? $post->type }} · {{ $post->is_published ? 'Нийтлэгдсэн' : 'Ноорог' }} · {{ $post->created_at->format('Y-m-d') }}</small></div>
                <div class="adm-actions">
                    @if($post->is_published)<a class="button button-ghost button-small" href="{{ route('news.show', $post) }}" target="_blank">Харах</a>@endif
                    <a class="button button-ghost button-small" href="{{ route('admin.posts.edit', $post) }}">Засах</a>
                    <form method="POST" action="{{ route('admin.posts.destroy', $post) }}" onsubmit="return confirm('Устгах уу?')">@csrf @method('DELETE')<button class="button button-ghost button-small" type="submit">Устгах</button></form>
                </div>
            </div>
        @empty<p class="muted">Нийтлэл алга.</p>@endforelse
    </div>
    {{ $posts->links() }}
</section>
@endsection
