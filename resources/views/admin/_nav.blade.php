<nav class="admin-nav" aria-label="Admin">
    <a href="{{ route('admin.dashboard') }}" @class(['active' => request()->routeIs('admin.dashboard')])>Dashboard</a>
    <a href="{{ route('admin.users.index') }}" @class(['active' => request()->routeIs('admin.users.*')])>Users</a>
    <a href="{{ route('admin.runs.index') }}" @class(['active' => request()->routeIs('admin.runs.*')])>Game runs</a>
    <a href="{{ route('editor.index') }}">Room editor</a>
</nav>
