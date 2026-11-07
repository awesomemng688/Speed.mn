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
    @if($post->type === 'tournament')
        <section class="panel tournament-signup">
            <div class="panel-heading"><div><p class="eyebrow">ТЭМЦЭЭН</p><h2>Оролцогчдын бүртгэл</h2></div><strong>{{ $registrations->count() }} баг / тоглогч</strong></div>
            @auth
                @if($registered)
                    <p class="admin-alert success">Та бүртгүүлсэн байна.</p>
                @elseif(! $post->event_at || $post->event_at->isFuture())
                    <form class="adm-form" method="POST" action="{{ route('tournaments.register', $post) }}">
                        @csrf
                        <label class="adm-field">Тоглогчийн нэр<input name="participant_name" value="{{ old('participant_name', auth()->user()->name) }}" maxlength="80" required></label>
                        <label class="adm-field">Багийн нэр (заавал биш)<input name="team_name" value="{{ old('team_name') }}" maxlength="80"></label>
                        <label class="adm-field">Холбоо барих (заавал биш)<input name="contact" value="{{ old('contact') }}" maxlength="100"></label>
                        <button class="button button-primary" type="submit">Бүртгүүлэх</button>
                    </form>
                @else
                    <p class="muted">Бүртгэл хаагдсан.</p>
                @endif
            @else
                <p><a class="button button-primary" href="{{ route('steam.login', ['redirect' => url()->current()]) }}">Steam-ээр нэвтэрч бүртгүүлэх</a></p>
            @endauth
            <div class="tournament-participants">
                @forelse($registrations as $registration)
                    <div class="adm-row"><strong>{{ $registration->team_name ?: $registration->participant_name }}</strong><small class="muted">{{ $registration->team_name ? $registration->participant_name.' · ' : '' }}{{ $registration->created_at->format('Y-m-d') }}</small></div>
                @empty
                    <p class="muted">Одоогоор бүртгэл алга.</p>
                @endforelse
            </div>
        </section>
    @endif
</section>
@endsection
