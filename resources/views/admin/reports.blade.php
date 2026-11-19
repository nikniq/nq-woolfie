@extends('layouts.app')

@section('title', 'Reports — Admin')

@section('content')
<section class="admin">
    <h2>Reports</h2>
    @include('admin._nav')
    <nav class="admin-tabs">
        @foreach ($periods as $d => $label)<a href="{{ route('admin.reports.index', ['days' => $d]) }}" @class(['active' => $days === $d])>{{ $label }}</a>@endforeach
    </nav>
    <p class="muted">{{ $report['from']->toFormattedDateString() }} – {{ $report['to']->toFormattedDateString() }}</p>
    <div class="admin-stats">
        @foreach ($report['totals'] as $label => $value)
            <div class="admin-stat"><strong>{{ number_format($value) }}</strong><span>{{ $label }}</span></div>
        @endforeach
    </div>

    <h3>Rooms</h3>
    <table>
        <thead><tr><th>Room</th><th>Runs</th><th>Cleared</th><th>Clear rate</th></tr></thead>
        <tbody>
        @forelse ($report['rooms'] as $r)
            <tr><td>{{ $r['name'] }}</td><td>{{ $r['runs'] }}</td><td>{{ $r['completed'] }}</td><td><span class="meter"><span style="width: {{ $r['rate'] }}%"></span></span> {{ $r['rate'] }}%</td></tr>
        @empty
            <tr><td colspan="4" class="muted">No runs in this period.</td></tr>
        @endforelse
        </tbody>
    </table>

    <h3>Most active players</h3>
    <table>
        <thead><tr><th>Player</th><th>Runs</th><th>Best score</th></tr></thead>
        <tbody>
        @forelse ($report['topPlayers'] as $p)
            <tr><td><a href="{{ route('player', $p['player_name']) }}">{{ $p['player_name'] }}</a></td><td>{{ $p['runs'] }}</td><td>{{ $p['best'] }}</td></tr>
        @empty
            <tr><td colspan="3" class="muted">No players in this period.</td></tr>
        @endforelse
        </tbody>
    </table>

    <h3>Exports</h3>
    <p>
        <a class="btn btn--sm" href="{{ route('admin.reports.export', 'users') }}">Users CSV</a>
        <a class="btn btn--sm" href="{{ route('admin.reports.export', 'runs') }}">Game runs CSV</a>
        <a class="btn btn--sm" href="{{ route('admin.reports.export', 'visits') }}">Visits CSV</a>
    </p>
    <p class="muted">Every Monday at 08:00 the scheduler emails this report (last 7 days) to all admins: <code>php artisan app:weekly-report</code>.</p>
</section>
@endsection
