<nav class="admin-nav" aria-label="Admin">
    @foreach ([
        'admin.dashboard' => 'Dashboard', 'admin.users.index' => 'Users', 'admin.runs.index' => 'Game runs',
        'admin.health' => 'Health', 'admin.monitoring' => 'Monitoring', 'admin.logs' => 'Logs',
        'admin.reports.index' => 'Reports', 'admin.marketing' => 'Marketing',
    ] as $route => $label)
        <a href="{{ route($route) }}" @class(['active' => request()->routeIs(\Illuminate\Support\Str::beforeLast($route, '.index').'*')])>{{ $label }}</a>
    @endforeach
    <a href="{{ route('editor.index') }}">Room editor</a>
</nav>
