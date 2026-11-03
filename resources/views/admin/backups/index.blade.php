@extends('layouts.app')
@section('title', 'Нөөцлөлт — Speed.mn')
@section('content')
<section class="page-head"><div class="shell"><p class="eyebrow">УДИРДЛАГА</p><div class="section-heading"><h1>Өгөгдлийн сангийн нөөцлөлт</h1></div><x-admin-nav /></div></section>
<section class="shell section admin-section">
    <div class="adm-stats">
        <div class="panel"><small class="muted">СҮҮЛИЙН АМЖИЛТТАЙ НӨӨЦЛӨЛТ</small><strong>{{ $lastSuccessAt?->timezone('Asia/Ulaanbaatar')->format('Y-m-d H:i') ?? 'байхгүй' }}</strong><small class="muted">{{ $lastSuccessAt?->diffForHumans() }}</small></div>
        <div class="panel"><small class="muted">ХАДГАЛАГДСАН ФАЙЛ</small><strong>{{ $backups->count() }}</strong></div>
    </div>
    <form method="POST" action="{{ route('admin.backups.run') }}" onsubmit="return confirm('Одоо нөөцлөлт хийх үү?')">@csrf<button class="button button-primary" type="submit">Одоо нөөцлөх</button></form>
    <div class="panel">
        @forelse($backups as $b)
            <div class="adm-row">
                <div><strong>{{ $b['name'] }}</strong><br><small class="muted">{{ $b['at']->timezone('Asia/Ulaanbaatar')->format('Y-m-d H:i') }} · {{ number_format($b['size'] / 1024, 1) }} KB</small></div>
                <a class="button button-ghost button-small" href="{{ route('admin.backups.download', $b['name']) }}">Татах</a>
            </div>
        @empty<p class="muted">Нөөцлөлтийн файл алга.</p>@endforelse
    </div>
</section>
@endsection
