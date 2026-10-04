@extends('layouts.app')
@section('title', 'Failed queue jobs — Speed.mn')
@section('content')
<section class="page-head"><div class="shell"><p class="eyebrow">ADMINISTRATION</p><h1>Failed queue jobs</h1><p class="muted">Inspect failures, retry recoverable jobs, or remove resolved entries.</p><x-admin-nav /></div></section>
<section class="shell section admin-section">
    @if(session('status'))<div class="admin-alert success" role="status">{{ session('status') }}</div>@endif
    @if(session('error'))<div class="admin-alert error" role="alert">{{ session('error') }}</div>@endif
    <form class="rank-search" method="GET" action="{{ route('admin.failed-jobs.index') }}" role="search">
        <label class="sr-only" for="failed-job-search">Search failed jobs</label>
        <input id="failed-job-search" type="search" name="search" value="{{ $search }}" placeholder="Search job, queue, or error">
        <button class="button button-primary" type="submit">Search</button>
        @if($search)<a class="text-link" href="{{ route('admin.failed-jobs.index') }}">Clear</a>@endif
    </form>
    <p class="result-count">{{ $jobs->total() }} failed jobs</p>
    <div class="panel admin-table-wrap"><table class="admin-table"><thead><tr><th>Job</th><th>Queue</th><th>Failed at</th><th>Error summary</th><th>Actions</th></tr></thead><tbody>
    @forelse($jobs as $job)
        <tr>
            <td><strong>{{ $job->job_name }}</strong><small>{{ $job->uuid }}</small></td>
            <td>{{ $job->queue }}</td>
            <td>{{ \Illuminate\Support\Carbon::parse($job->failed_at)->diffForHumans() }}</td>
            <td><small>{{ $job->exception_summary }}</small></td>
            <td><div class="admin-actions">
                <form method="POST" action="{{ route('admin.failed-jobs.retry', $job->uuid) }}" onsubmit="return confirm('Retry this failed job?')">@csrf<button class="text-button" type="submit">Retry</button></form>
                <form method="POST" action="{{ route('admin.failed-jobs.destroy', $job->uuid) }}" onsubmit="return confirm('Remove this failed job record?')">@csrf @method('DELETE')<button class="text-button danger" type="submit">Forget</button></form>
            </div></td>
        </tr>
    @empty
        <tr><td colspan="5" class="empty">No failed jobs found.</td></tr>
    @endforelse
    </tbody></table></div>
    <div class="pagination">{{ $jobs->links() }}</div>
</section>
@endsection