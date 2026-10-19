@extends('layouts.app')

@section('title', config('app.name').' — Leaderboard')

@section('content')
<section class="leaderboard">
    <p class="stats">
        Castle escapes: <strong>{{ $stats['escapes'] }}</strong> &middot;
        Rooms cleared: <strong>{{ $stats['completed'] }}</strong> &middot;
        Caught: <strong>{{ $stats['caught'] }}</strong>
    </p>

    <h2>Today's challenge <span class="muted">{{ $challenge['date'] }}</span></h2>
    <p>{{ $challenge['title'] }}: @foreach ($challenge['modifiers'] as $m)<span class="badge" title="{{ $m['description'] }}">{{ $m['label'] }}</span> @endforeach <span class="muted">Press D on the title screen to play it.</span></p>
    @if ($dailyBoard->isEmpty())
        <p class="muted">No attempts yet today.</p>
    @else
        <table>
            <thead><tr><th>#</th><th>Player</th><th>Outcome</th><th>Score</th><th>Time</th></tr></thead>
            <tbody>
            @foreach ($dailyBoard as $r)
                <tr><td>{{ $loop->iteration }}</td><td><a href="{{ route('player', $r->player_name) }}">{{ $r->player_name }}</a></td><td>{{ $r->outcome === 'escaped' ? '🏰 Escaped' : '💀 Failed' }}</td><td>{{ $r->score }}</td><td>{{ number_format($r->time_ms / 1000, 1) }}s</td></tr>
            @endforeach
            </tbody>
        </table>
    @endif

    <h2>Hall of fame</h2>
    @if ($hallOfFame->isEmpty())
        <p class="muted">No achievements yet. Escape the castle to earn the first.</p>
    @else
        <table class="fame">
            <thead><tr><th>Player</th><th>Achievements</th></tr></thead>
            <tbody>
            @foreach ($hallOfFame as $player => $keys)
                <tr>
                    <td><a href="{{ route('player', $player) }}">{{ $player }}</a></td>
                    <td>@foreach ($keys as $key)<span class="badge" title="{{ $catalogue[$key]['description'] ?? '' }}">{{ $catalogue[$key]['icon'] ?? '' }} {{ $catalogue[$key]['title'] ?? $key }}</span> @endforeach</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
    <details class="legend">
        <summary>All achievements</summary>
        <ul>
            @foreach ($catalogue as $key => $def)
                <li><span class="badge">{{ $def['icon'] }} {{ $def['title'] }}</span> {{ $def['description'] }}</li>
            @endforeach
        </ul>
    </details>

    <h2>Fastest full-castle runs</h2>
    @if ($campaigns->isEmpty())
        <p class="muted">Nobody has escaped all {{ $levels->count() }} levels in one run yet.</p>
    @else
        <table>
            <thead>
                <tr><th>#</th><th>Player</th><th>Difficulty</th><th>Total score</th><th>Total time</th><th>When</th></tr>
            </thead>
            <tbody>
            @foreach ($campaigns as $c)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td><a href="{{ route('player', $c->player_name) }}">{{ $c->player_name }}</a></td>
                    <td><span class="diff {{ $c->difficulty }}">{{ $c->difficulty }}</span></td>
                    <td>{{ $c->total_score }}</td>
                    <td>{{ number_format($c->total_time / 1000, 2) }}s</td>
                    <td>{{ \Illuminate\Support\Carbon::parse($c->finished_at)->diffForHumans() }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

    @foreach ($levels as $level)
        <h2>Level {{ $level->number }} — {{ $level->name }}</h2>
        @php($runs = $bestRuns->get($level->id, collect()))
        @if ($runs->isEmpty())
            <p class="muted">No escapes yet. Be the first.</p>
        @else
            <table>
                <thead>
                    <tr><th>#</th><th>Player</th><th>Difficulty</th><th>Score</th><th>Time</th><th>When</th></tr>
                </thead>
                <tbody>
                @foreach ($runs as $run)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td><a href="{{ route('player', $run->player_name) }}">{{ $run->player_name }}</a></td>
                        <td><span class="diff {{ $run->difficulty }}">{{ $run->difficulty }}</span></td>
                        <td>{{ $run->score }}</td>
                        <td>{{ number_format($run->time_ms / 1000, 2) }}s</td>
                        <td>{{ $run->created_at->diffForHumans() }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    @endforeach
</section>
@endsection
