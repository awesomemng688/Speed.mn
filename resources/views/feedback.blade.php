@extends('layouts.app')

@section('title', 'Санал хүсэлт — Speed.mn')
@section('description', 'Speed.mn-ийн сервер болон үйлчилгээг сайжруулах санал, хүсэлтээ илгээгээрэй.')
@section('content')
<section class="page-head"><div class="shell"><a class="back" href="{{ route('home') }}">← Нүүр хуудас</a><p class="eyebrow">БИДЭНД ТУСЛААРАЙ</p><h1>Санал хүсэлт</h1><p class="muted">Таны санал Speed.mn-ийг сайжруулахад тусална.</p></div></section>
<section class="shell section">
    <form class="panel" method="POST" action="{{ route('feedback.store') }}" style="display:grid;gap:16px;max-width:720px;margin:auto;padding:24px">
        @csrf
        @if(session('status'))<div class="admin-alert success" role="status">{{ session('status') }}</div>@endif
        <label class="filter-field" for="feedback-name">Нэр
            <input id="feedback-name" name="name" value="{{ old('name', auth()->user()?->name) }}" maxlength="100" required autocomplete="name">
            @error('name')<small role="alert">{{ $message }}</small>@enderror
        </label>
        <label class="filter-field" for="feedback-email">И-мэйл <span>(заавал биш)</span>
            <input id="feedback-email" type="email" name="email" value="{{ old('email', auth()->user()?->email) }}" maxlength="255" autocomplete="email">
            @error('email')<small role="alert">{{ $message }}</small>@enderror
        </label>
        <label class="filter-field" for="feedback-message">Санал хүсэлт
            <textarea id="feedback-message" name="message" rows="7" minlength="10" maxlength="3000" required>{{ old('message') }}</textarea>
            @error('message')<small role="alert">{{ $message }}</small>@enderror
        </label>
        <button class="button button-primary" type="submit">Илгээх <span>→</span></button>
    </form>
</section>
@endsection
