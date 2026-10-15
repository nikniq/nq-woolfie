@extends('layouts.app')

@section('title', $name.' — '.config('app.name'))

@section('content')
<section class="player">
    <h2>{{ $name }}</h2>
    <p class="stats">
        Campaigns: <strong>{{ $summary['campaigns'] }}</strong> &middot;
        Escapes: <strong>{{ $summary['escapes'] }}</strong> &middot;
        Rooms cleared: <strong>{{ $summary['rooms_cleared'] }}</strong> &middot;
        Caught: <strong>{{ $summary['caught'] }}</strong> &middot;
        Guards shot: <strong>{{ $summary['kills'] }}</strong> &middot;
        Alarms: <strong>{{ $summary['alarms'] }}</strong>
        @if ($summary['best_score'] !== null)
            &middot; Best escape: <strong>{{ $summary['best_score'] }}</strong> pts in <strong>{{ number_format($summary['best_time'] / 1000, 1) }}s</strong>
        @endif
    </p>

    <h3>Achievements <span class="muted">({{ $held->count() }} / {{ count($catalogue) }})</span></h3>
    <ul class="badges">
        @foreach ($catalogue as $key => $def)
            @php($earned = $held->firstWhere('key', $key))
            <li class="{{ $earned ? 'earned' : 'locked' }}" title="{{ $def['description'] }}">
                <span class="badge">{{ $def['icon'] }} {{ $def['title'] }}</span>
                <span class="muted">{{ $earned ? $earned->earned_at->diffForHumans() : $def['description'] }}</span>
            </li>
        @endforeach
    </ul>

    <h3>Rooms</h3>
    <table>
        <thead><tr><th>Room</th><th>Attempts</th><th>Clears</th><th>Best time</th><th>Best score</th></tr></thead>
        <tbody>
        @foreach ($bestRooms as $row)
            <tr>
                <td>{{ $row['level']->number }}. {{ $row['level']->name }}</td>
                <td>{{ $row['attempts'] }}</td>
                <td>{{ $row['clears'] }}</td>
                <td>{{ $row['best_time'] !== null ? number_format($row['best_time'] / 1000, 2).'s' : '—' }}</td>
                <td>{{ $row['best_score'] ?? '—' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <h3>Campaigns</h3>
    @if ($results->isEmpty())
        <p class="muted">No finished campaigns yet.</p>
    @else
        <table>
            <thead><tr><th>When</th><th>Outcome</th><th>Difficulty</th><th>Score</th><th>Time</th><th>Rooms</th><th>Shot</th><th>Alarms</th><th>Lives lost</th></tr></thead>
            <tbody>
            @foreach ($results as $r)
                <tr>
                    <td>{{ $r->created_at->diffForHumans() }}</td>
                    <td>{{ $r->outcome === 'escaped' ? '🏰 Escaped' : '💀 Failed' }}</td>
                    <td><span class="diff {{ $r->difficulty }}">{{ $r->difficulty }}</span></td>
                    <td>{{ $r->score }}</td>
                    <td>{{ number_format($r->time_ms / 1000, 1) }}s</td>
                    <td>{{ $r->rooms_cleared }}/{{ $r->rooms_total }}</td>
                    <td>{{ $r->kills }}</td>
                    <td>{{ $r->alarms }}</td>
                    <td>{{ $r->lives_lost }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</section>
@endsection
