@extends('layouts.app')
@section('title', 'Manage administrators — Speed.mn')
@section('content')
<section class="page-head"><div class="shell"><p class="eyebrow">ADMINISTRATION</p><div class="section-heading"><h1>Administrators</h1></div><p class="muted">Only grant access to people you trust with server controls.</p><x-admin-nav /></div></section>
<section class="shell section admin-section">
    @if(session('status'))<div class="admin-alert success" role="status">{{ session('status') }}</div>@endif
    @if(session('error'))<div class="admin-alert error" role="alert">{{ session('error') }}</div>@endif
    <div class="admin-summary"><div class="panel"><small>Administrators</small><strong>{{ $adminCount }}</strong></div><div class="panel"><small>Registered users</small><strong>{{ $users->total() }}</strong></div></div>
    <form class="rank-search" method="GET" action="{{ route('admin.users.index') }}" role="search">
        <label class="sr-only" for="admin-user-search">Search users</label>
        <input id="admin-user-search" type="search" name="search" value="{{ $search }}" placeholder="Search name, email, or Steam ID">
        <button class="button button-primary" type="submit">Search</button>
        @if($search)<a class="text-link" href="{{ route('admin.users.index') }}">Clear</a>@endif
    </form>
    <div class="panel admin-table-wrap"><table class="admin-table"><thead><tr><th>User</th><th>Steam ID</th><th>Role</th><th>Action</th></tr></thead><tbody>
    @forelse($users as $user)
        <tr>
            <td><strong>{{ $user->name }}</strong><small>{{ $user->email }}</small></td>
            <td>{{ $user->steam_id ?: 'Steam not connected' }}</td>
            <td><span class="admin-badge {{ $user->is_admin ? 'enabled' : 'disabled' }}">{{ $user->is_admin ? 'Administrator' : 'User' }}</span></td>
            <td>
                @if($user->id === auth()->id())
                    <span class="muted">You</span>
                @else
                    <form method="POST" action="{{ route('admin.users.toggle', $user) }}" onsubmit="return confirm('{{ $user->is_admin ? 'Remove this administrator access?' : 'Grant administrator access to this user?' }}')">
                        @csrf
                        @method('PATCH')
                        <button class="text-button {{ $user->is_admin ? 'danger' : '' }}" type="submit">{{ $user->is_admin ? 'Remove admin' : 'Make admin' }}</button>
                    </form>
                @endif
            </td>
        </tr>
    @empty
        <tr><td colspan="4" class="empty">No users found.</td></tr>
    @endforelse
    </tbody></table></div>
    <div class="pagination">{{ $users->links() }}</div>
</section>
@endsection