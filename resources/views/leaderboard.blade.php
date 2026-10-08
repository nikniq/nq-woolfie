@extends('layouts.app')

@section('title', config('app.name').' — Leaderboard')

@section('content')
<section class="leaderboard">
    <p class="stats">
        Escapes: <strong>{{ $stats['completed'] }}</strong> &middot;
        Caught: <strong>{{ $stats['caught'] }}</strong>
    </p>

    <h2>Full castle escapes</h2>
    @if ($campaigns->isEmpty())
        <p class="muted">Nobody has escaped all {{ $levels->count() }} levels in one run yet.</p>
    @else
        <table>
            <thead>
                <tr><th>#</th><th>Player</th><th>Total score</th><th>Total time</th><th>When</th></tr>
            </thead>
            <tbody>
            @foreach ($campaigns as $c)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $c->player_name }}</td>
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
                    <tr><th>#</th><th>Player</th><th>Score</th><th>Time</th><th>When</th></tr>
                </thead>
                <tbody>
                @foreach ($runs as $run)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $run->player_name }}</td>
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
