@extends('layouts.app')
@section('title', 'Admin audit log — Speed.mn')
@section('content')
<section class="page-head"><div class="shell"><p class="eyebrow">ADMINISTRATION</p><h1>Audit log</h1><p class="muted">Server configuration, admin access, and failed-job actions.</p><x-admin-nav /></div></section>
<section class="shell section admin-section">
    <form class="rank-search" method="GET" action="{{ route('admin.audit.index') }}" role="search">
        <label class="sr-only" for="audit-search">Search audit log</label>
        <input id="audit-search" type="search" name="search" value="{{ $search }}" placeholder="Search actor, event, or subject">
        <label class="filter-field"><span class="sr-only">Event</span><select name="event"><option value="">All events</option>@foreach($events as $eventOption)<option value="{{ $eventOption }}" @selected($event === $eventOption)>{{ str_replace('.', ' · ', $eventOption) }}</option>@endforeach</select></label>
        <button class="button button-primary" type="submit">Filter</button>
        @if($search || $event)<a class="text-link" href="{{ route('admin.audit.index') }}">Clear</a>@endif
    </form>
    <p class="result-count">{{ $logs->total() }} audit events</p>
    <div class="panel admin-table-wrap"><table class="admin-table"><thead><tr><th>Time</th><th>Actor</th><th>Event</th><th>Subject</th><th>Details</th><th>Source IP</th></tr></thead><tbody>
    @forelse($logs as $log)
        @php($loggedFields = $log->details['changed_fields'] ?? $log->details['fields'] ?? array_keys($log->details ?? []))
        <tr><td>{{ $log->created_at?->timezone('Asia/Ulaanbaatar')->format('Y-m-d H:i:s') }} (UB)</td><td>{{ $log->actor?->name ?? 'Deleted account' }}</td><td>{{ $log->event }}</td><td>{{ $log->subject_label ?: '—' }}</td><td><small>{{ implode(', ', $loggedFields) ?: '—' }}</small></td><td>{{ $log->ip_address ?: '—' }}</td></tr>
    @empty
        <tr><td colspan="6" class="empty">No audit events found.</td></tr>
    @endforelse
    </tbody></table></div>
    <div class="pagination">{{ $logs->links() }}</div>
</section>
@endsection