@extends('layouts.app')

@section('title', 'Health — Admin')

@section('content')
<section class="admin">
    <h2>Health</h2>
    @include('admin._nav')
    <p @class(['admin-banner', 'admin-banner--ok' => $healthy, 'admin-banner--bad' => ! $healthy])>
        {{ $healthy ? 'All systems healthy.' : 'Some checks are failing.' }}
        <span class="muted">Checked {{ now()->format('H:i:s') }}</span>
    </p>
    <table>
        <thead><tr><th>Check</th><th>Status</th><th>Detail</th></tr></thead>
        <tbody>
        @foreach ($results as $r)
            <tr><td>{{ $r['name'] }}</td><td><span @class(['pill', 'pill--ok' => $r['ok'], 'pill--bad' => ! $r['ok']])>{{ $r['ok'] ? 'OK' : 'FAIL' }}</span></td><td>{{ $r['detail'] }}</td></tr>
        @endforeach
        </tbody>
    </table>
    @if ($commit)<p class="muted">Deployed: {{ $commit }}</p>@endif
    <h3>Uptime monitoring</h3>
    <p class="muted">
        <code>/up</code> answers 200 when the app boots (public).
        @if (config('game.health_token') !== '')
            <code>{{ route('health') }}?token=…</code> runs every check above and answers 503 if any fail; point an uptime monitor at it.
        @else
            Set <code>HEALTH_TOKEN</code> in .env to enable <code>/health?token=…</code>, which runs every check and answers 503 when one fails.
        @endif
        The scheduler also runs <code>php artisan app:health</code> every five minutes and logs a critical entry on failure; make sure cron runs <code>php artisan schedule:run</code> every minute.
    </p>
</section>
@endsection
