<nav class="tabs admin-tabs" aria-label="Administration">
    <a class="{{ request()->routeIs('admin.servers.*') ? 'active' : '' }}" href="{{ route('admin.servers.index') }}">Servers</a>
    <a class="{{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}">Administrators</a>
    <a class="{{ request()->routeIs('admin.failed-jobs.*') ? 'active' : '' }}" href="{{ route('admin.failed-jobs.index') }}">Failed jobs</a>
    <a class="{{ request()->routeIs('admin.audit.*') ? 'active' : '' }}" href="{{ route('admin.audit.index') }}">Audit log</a>
</nav>