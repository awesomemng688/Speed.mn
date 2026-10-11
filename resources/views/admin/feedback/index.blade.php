@extends('layouts.app')
@section('title', 'Санал хүсэлт — Админ — Speed.mn')
@section('content')
<section class="page-head"><div class="shell"><p class="eyebrow">АДМИН УДИРДЛАГА</p><h1>Санал хүсэлт</h1><p class="muted">Хэрэглэгчдийн илгээсэн санал хүсэлт.</p><x-admin-nav /></div></section>
<section class="shell section admin-section">
    @if(session('status'))<div class="admin-alert success" role="status">{{ session('status') }}</div>@endif
    <p class="result-count">{{ $feedback->total() }} санал хүсэлт</p>
    <div class="panel admin-table-wrap"><table class="admin-table"><thead><tr><th>Илгээгч</th><th>Санал хүсэлт</th><th>Ирсэн</th><th>Төлөв</th></tr></thead><tbody>
    @forelse($feedback as $item)
        <tr>
            <td><strong>{{ $item->name }}</strong>@if($item->email)<small><a href="mailto:{{ $item->email }}">{{ $item->email }}</a></small>@endif</td>
            <td style="white-space:pre-wrap;min-width:260px">{{ $item->message }}</td>
            <td>{{ $item->created_at->timezone('Asia/Ulaanbaatar')->format('Y-m-d H:i') }}</td>
            <td><form method="POST" action="{{ route('admin.feedback.update', $item) }}" style="display:flex;gap:8px;align-items:center">@csrf @method('PATCH')
                <label class="sr-only" for="feedback-status-{{ $item->id }}">Төлөв</label>
                <select id="feedback-status-{{ $item->id }}" name="status"><option value="new" @selected($item->status === 'new')>Шинэ</option><option value="reviewing" @selected($item->status === 'reviewing')>Шалгаж байна</option><option value="resolved" @selected($item->status === 'resolved')>Шийдвэрлэсэн</option></select>
                <button class="text-button" type="submit">Хадгалах</button>
            </form></td>
        </tr>
    @empty
        <tr><td colspan="4" class="empty">Санал хүсэлт ирээгүй байна.</td></tr>
    @endforelse
    </tbody></table></div>
    <div class="pagination">{{ $feedback->links() }}</div>
</section>
@endsection
