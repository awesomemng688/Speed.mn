@extends('layouts.app')
@section('title', ($post->exists ? 'Нийтлэл засах' : 'Шинэ нийтлэл').' — Удирдлага')
@section('content')
<section class="page-head"><div class="shell"><p class="eyebrow">УДИРДЛАГА</p><div class="section-heading"><h1>{{ $post->exists ? 'Нийтлэл засах' : 'Шинэ нийтлэл' }}</h1></div><x-admin-nav /></div></section>
<section class="shell section admin-section">
    @if($errors->any())<div class="admin-alert error" role="alert">@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div>@endif
    <form class="panel adm-form" method="POST" action="{{ $post->exists ? route('admin.posts.update', $post) : route('admin.posts.store') }}">
        @csrf @if($post->exists) @method('PUT') @endif
        <label class="adm-field">Төрөл<select name="type">@foreach(\App\Models\Post::TYPES as $v => $l)<option value="{{ $v }}" @selected(old('type', $post->type) === $v)>{{ $l }}</option>@endforeach</select></label>
        <label class="adm-field">Гарчиг<input name="title" value="{{ old('title', $post->title) }}" maxlength="160" required></label>
        <label class="adm-field">Товч тайлбар<input name="excerpt" value="{{ old('excerpt', $post->excerpt) }}" maxlength="300"></label>
        <label class="adm-field">Агуулга<textarea name="body" rows="12" required>{{ old('body', $post->body) }}</textarea></label>
        <label class="adm-field">Зургийн линк (заавал биш)<input type="url" name="image_url" value="{{ old('image_url', $post->image_url) }}"></label>
        <label class="adm-field">Тэмцээний огноо (заавал биш)<input type="datetime-local" name="event_at" value="{{ old('event_at', $post->event_at?->format('Y-m-d\TH:i')) }}"></label>
        <label class="adm-check"><input type="checkbox" name="is_published" value="1" @checked(old('is_published', $post->is_published))> Нийтлэх</label>
        <div><button class="button button-primary" type="submit">Хадгалах</button> <a class="button button-ghost" href="{{ route('admin.posts.index') }}">Буцах</a></div>
    </form>
</section>
@endsection
