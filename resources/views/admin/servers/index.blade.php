@extends('layouts.app')
@section('title', 'Сервер удирдах — Speed.mn')
@section('content')
<section class="page-head"><div class="shell"><p class="eyebrow">УДИРДЛАГА</p><div class="section-heading admin-dashboard-heading"><div><h1>Серверүүд</h1><p class="muted">Сервер нэмэх, мэдээлэл засах, холболтыг шалгах.</p></div><a class="button button-primary" href="{{ route('admin.servers.create') }}">＋ Сервер нэмэх</a></div><x-admin-nav /></div></section>
<section class="shell section admin-section">
    @if(session('status'))<div class="admin-alert success">{{ session('status') }}</div>@endif
    @if(session('connection_success'))<div class="admin-alert success">{{ session('connection_success') }}</div>@endif
    @if(session('connection_error'))<div class="admin-alert error">{{ session('connection_error') }}</div>@endif
    <div class="admin-alert {{ $pollMonitor['scheduler_healthy'] && $pollMonitor['worker_healthy'] && $pollMonitor['backup_healthy'] && $pollMonitor['smoke_passed'] && $pollMonitor['failed_jobs'] === 0 ? 'success' : 'error' }}" role="status">
        Хуваарь: <strong>{{ $pollMonitor['scheduler_healthy'] ? 'ажиллаж байна' : 'илрээгүй' }}</strong>
        · Ажлын worker: <strong>{{ $pollMonitor['worker_healthy'] ? 'ажиллаж байна' : 'сүүлийн ажил алга' }}</strong>
        · Өдрийн нөөцлөлт: <strong>{{ $pollMonitor['backup_healthy'] ? 'хэвийн' : 'хуучирсан эсвэл алга' }}</strong>
        · HTTP шалгалт: <strong>{{ $pollMonitor['smoke_passed'] ? 'амжилттай' : 'бүртгэгдээгүй' }}</strong>
        · Хуучирсан төлөв: <strong>{{ $pollMonitor['stale_servers'] }}</strong>
        · Сүүлийн 1 цагийн алдаа: <strong>{{ $pollMonitor['failed_jobs'] }}</strong>
        <small>Сүүлд дараалалд орсон: {{ $pollMonitor['last_dispatched_at']?->diffForHumans() ?? 'байхгүй' }} · Сүүлд дууссан: {{ $pollMonitor['last_completed_at']?->diffForHumans() ?? 'байхгүй' }} · Сүүлд нөөцөлсөн: {{ $pollMonitor['last_backup_at']?->diffForHumans() ?? 'байхгүй' }} · HTTP шалгалт: {{ $pollMonitor['last_smoke_at']?->diffForHumans() ?? 'байхгүй' }}</small>
    </div>
    @if($pollMonitor['last_failed_at'])<div class="admin-alert error" role="alert">Сервер шалгах сүүлийн ажил амжилтгүй: {{ $pollMonitor['last_failed_at']->diffForHumans() }}</div>@endif
    <div class="admin-summary">
        <div class="panel"><small>Нийт сервер</small><strong>{{ $summary['total'] }}</strong></div>
        <div class="panel"><small>Идэвхтэй</small><strong>{{ $summary['enabled'] }}</strong></div>
        <div class="panel"><small>Одоо онлайн</small><strong class="admin-online">{{ $summary['online'] }}</strong></div>
        <div class="panel"><small>Тоглож буй хүн</small><strong>{{ $summary['players'] }}</strong></div>
    </div>
    <form class="server-filters" method="GET" action="{{ route('admin.servers.index') }}">
        <label class="search-box"><span aria-hidden="true">⌕</span><input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Нэр, IP эсвэл порт хайх" aria-label="Сервер хайх"></label>
        <label class="filter-field"><span>Тоглоом</span><select name="game"><option value="">Бүх тоглоом</option><option value="cs2" @selected(($filters['game'] ?? '') === 'cs2')>CS2</option><option value="cs16" @selected(($filters['game'] ?? '') === 'cs16')>CS 1.6</option></select></label>
        <label class="filter-field"><span>Төлөв</span><select name="status"><option value="">Бүх төлөв</option>@foreach(['online' => 'Онлайн', 'offline' => 'Офлайн', 'stale' => 'Хуучирсан', 'unknown' => 'Мэдээлэлгүй'] as $value => $label)<option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></label>
        <label class="filter-field"><span>Шалгалт</span><select name="poll"><option value="">Бүх хугацаа</option><option value="fresh" @selected(($filters['poll'] ?? '') === 'fresh')>Саяхан</option><option value="stale" @selected(($filters['poll'] ?? '') === 'stale')>Хуучирсан</option><option value="never" @selected(($filters['poll'] ?? '') === 'never')>Шалгаагүй</option></select></label>
        <label class="filter-field"><span>Алдаа</span><select name="error"><option value="">Бүгд</option><option value="yes" @selected(($filters['error'] ?? '') === 'yes')>Алдаатай</option><option value="no" @selected(($filters['error'] ?? '') === 'no')>Алдаагүй</option></select></label>
        <label class="filter-field"><span>Эрэмбэ</span><select name="sort"><option value="">Тоглоом, нэрээр</option><option value="poll_newest" @selected(($filters['sort'] ?? '') === 'poll_newest')>Сүүлд шалгасан</option><option value="poll_oldest" @selected(($filters['sort'] ?? '') === 'poll_oldest')>Удаан шалгаагүй</option></select></label>
        <button class="button button-primary" type="submit">Шүүх</button>
        @if(request()->query())<a class="text-link" href="{{ route('admin.servers.index') }}">Арилгах</a>@endif
    </form>
    <div class="panel admin-table-wrap"><table class="admin-table"><thead><tr><th>Сервер</th><th>Тоглоом</th><th>Хаяг</th><th>Төлөв</th><th>Сүүлд шалгасан</th><th>Сүүлд амжилттай</th><th>Сүүлийн алдаа</th><th>Нийтэд харагдах</th><th>Үйлдэл</th></tr></thead><tbody>
    @forelse($servers as $server)
        @php($statusState = \App\Models\ServerStatus::stateOf($server->latestStatus))
        <tr><td><strong>{{ $server->name }}</strong><small>{{ $server->region ?: 'Бүс нутаггүй' }}</small></td><td>{{ strtoupper($server->game) }}</td><td>{{ $server->address }}</td><td><span class="status {{ $statusState }}"><i></i>{{ ['online' => 'Онлайн', 'offline' => 'Офлайн', 'stale' => 'Хуучирсан', 'unknown' => 'Тодорхойгүй'][$statusState] ?? $statusState }}</span></td><td>{{ $server->last_polled_at?->diffForHumans() ?? 'Шалгаагүй' }}</td><td>{{ $server->last_successful_poll_at?->diffForHumans() ?? 'Байхгүй' }}</td><td><small>{{ $server->last_query_error ?: '—' }}</small></td><td><span class="admin-badge {{ $server->enabled ? 'enabled' : 'disabled' }}">{{ $server->enabled ? 'Идэвхтэй' : 'Идэвхгүй' }}</span></td><td><div class="admin-actions"><a class="text-link" href="{{ route('admin.servers.edit', $server) }}">Засах</a><form method="POST" action="{{ route('admin.servers.toggle', $server) }}" onsubmit="return confirm('{{ $server->enabled ? 'Энэ серверийг идэвхгүй болгох уу?' : 'Энэ серверийг идэвхжүүлэх үү?' }}')">@csrf @method('PATCH')<button class="text-button" type="submit">{{ $server->enabled ? 'Нуух' : 'Идэвхжүүлэх' }}</button></form>@if($server->enabled)<form method="POST" action="{{ route('admin.servers.poll', $server) }}">@csrf<button class="text-button" type="submit">Шалгах</button></form>@endif<form method="POST" action="{{ route('admin.servers.test', $server) }}">@csrf<button class="text-button" type="submit">Холболт тестлэх</button></form><form method="POST" action="{{ route('admin.servers.destroy', $server) }}" onsubmit="return confirm('Сервер болон түүний төлөвийн түүхийг устгах уу?')">@csrf @method('DELETE')<button class="text-button danger" type="submit">Устгах</button></form></div></td></tr>
    @empty<tr><td colspan="9" class="empty">Одоогоор сервер бүртгээгүй байна. Баруун дээд талын “Сервер нэмэх” товчоор эхлүүлнэ үү.</td></tr>@endforelse
    </tbody></table></div>{{ $servers->links() }}
</section>
@endsection
