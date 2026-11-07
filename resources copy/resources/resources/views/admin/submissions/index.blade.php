@extends('layouts.app')
@section('title', 'Submissions — Speed.mn')
@section('content')
<section class="page-head"><div class="shell"><p class="eyebrow">ADMINISTRATION</p><div class="section-heading"><h1>Server requests</h1></div><x-admin-nav /></div></section>
<section class="shell section admin-section">
    @if(session('status'))<div class="admin-alert">{{ session('status') }}</div>@endif
    <div class="panel"><div class="panel-heading"><h2>Хүлээгдэж буй ({{ $pending->count() }})</h2></div>
        @forelse($pending as $s)
        <div class="panel"><strong>{{ $s->name }}</strong> <span class="muted">{{ $s->game === 'cs2' ? 'CS2' : 'CS 1.6' }} · {{ $s->ip }}:{{ $s->port }} · {{ $s->max_players }} slots · {{ $s->region }}</span>
            <p class="muted">{{ $s->user?->name }} · {{ $s->note }}</p>
            <form method="POST" action="{{ route('admin.submissions.approve', $s) }}" style="display:inline">@csrf<button class="button button-primary" type="submit">Батлах</button></form>
            <form method="POST" action="{{ route('admin.submissions.reject', $s) }}" style="display:inline">@csrf<button class="button button-ghost" type="submit">Татгалзах</button></form>
        </div>
        @empty<p class="muted">Хүсэлт алга.</p>@endforelse
    </div>
    <div class="panel"><div class="panel-heading"><h2>Сүүлд шийдвэрлэсэн</h2></div>
        @foreach($reviewed as $s)<p class="muted">{{ $s->name }} · {{ $s->ip }}:{{ $s->port }} · {{ $s->status }}</p>@endforeach
    </div>
</section>
@endsection
