@extends('layouts.app')
@section('title', 'Чансаа удирдлага — Speed.mn')
@section('content')
<section class="page-head"><div class="shell"><p class="eyebrow">УДИРДЛАГА</p><div class="section-heading"><h1>Чансаа удирдлага</h1></div><x-admin-nav /></div></section>
<section class="shell section admin-section">
    <div class="rank-tabs">
        @foreach($sources as $key => $s)<a class="{{ $category === $key ? 'active' : '' }}" href="{{ route('admin.ranks.index', ['category' => $key]) }}">{{ $s['label'] }}</a>@endforeach
    </div>
    <form class="rank-search" method="GET" action="{{ route('admin.ranks.index') }}">
        <input type="hidden" name="category" value="{{ $category }}">
        <input type="search" name="search" value="{{ $search }}" placeholder="Нэр хайх..." autocomplete="off">
        <button class="button button-primary" type="submit">Хайх</button>
    </form>
    @if($error)<div class="admin-alert error">{{ $error }}</div>@endif
    @if($players)
    <div class="rank-table-wrap">
        <table class="rank-table">
            <thead><tr><th>Нэр</th><th>Steam ID</th><th>{{ $source['order'] }}</th><th>Сүүлд</th><th></th></tr></thead>
            <tbody>
            @forelse($players as $p)
                <tr>
                    <td>{{ $p->Nick ?? $p->Player }}</td>
                    <td>{{ $p->{'Steam ID'} ?? '—' }}</td>
                    <td>{{ number_format((int) ($p->{$source['order']} ?? 0)) }}</td>
                    <td>{{ $p->{'Last Login'} ?? '—' }}</td>
                    <td>
                        <form method="POST" action="{{ route('admin.ranks.reset', $category) }}" onsubmit="var v=prompt('Энэ тоглогчийн бүх статистикийг 0 болгоно. Баталгаажуулахын тулд RESET гэж бичнэ үү:');if(v!=='RESET'){return false;}this.confirm.value=v;return true;">
                            @csrf
                            <input type="hidden" name="player" value="{{ $p->Player }}">
                            <input type="hidden" name="confirm" value="">
                            <button class="button button-ghost button-small" type="submit">Тэглэх</button>
                        </form>
                    </td>
                </tr>
            @empty<tr><td colspan="5" class="muted">Тоглогч олдсонгүй.</td></tr>@endforelse
            </tbody>
        </table>
    </div>
    {{ $players->links() }}
    @endif
</section>
@endsection
