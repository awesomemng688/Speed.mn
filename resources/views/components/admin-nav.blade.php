<nav class="tabs admin-tabs" aria-label="Удирдлага">
    <a class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">Хянах самбар</a>
    <a class="{{ request()->routeIs('admin.settings.*') ? 'active' : '' }}" href="{{ route('admin.settings.edit') }}">Сайтын тохиргоо</a>
    <a class="{{ request()->routeIs('admin.servers.*') ? 'active' : '' }}" href="{{ route('admin.servers.index') }}">Серверүүд</a>
    <a class="{{ request()->routeIs('admin.submissions.*') ? 'active' : '' }}" href="{{ route('admin.submissions.index') }}">Хүсэлтүүд</a>
    <a class="{{ request()->routeIs('admin.posts.*') ? 'active' : '' }}" href="{{ route('admin.posts.index') }}">Мэдээ / Тэмцээн</a>
    <a class="{{ request()->routeIs('admin.vip.*') ? 'active' : '' }}" href="{{ route('admin.vip.index') }}">VIP</a>
    <a class="{{ request()->routeIs('admin.ranks.*') ? 'active' : '' }}" href="{{ route('admin.ranks.index') }}">Чансаа</a>
    <a class="{{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}">Админууд</a>
    <a class="{{ request()->routeIs('admin.failed-jobs.*') ? 'active' : '' }}" href="{{ route('admin.failed-jobs.index') }}">Алдаатай ажлууд</a>
    <a class="{{ request()->routeIs('admin.audit.*') ? 'active' : '' }}" href="{{ route('admin.audit.index') }}">Үйлдлийн бүртгэл</a>
    <a class="{{ request()->routeIs('admin.demos.*') ? 'active' : '' }}" href="{{ route('admin.demos.index') }}">Демо бичлэг</a>
    <a class="{{ request()->routeIs('admin.backups.*') ? 'active' : '' }}" href="{{ route('admin.backups.index') }}">Нөөцлөлт</a>
    <a class="{{ request()->routeIs('admin.two-factor.*') ? 'active' : '' }}" href="{{ route('admin.two-factor.setup') }}">Аюулгүй байдал</a>
</nav>
