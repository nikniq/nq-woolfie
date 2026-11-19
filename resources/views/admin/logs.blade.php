@extends('layouts.app')

@section('title', 'Logs — Admin')

@section('content')
<section class="admin">
    <h2>Logs</h2>
    @include('admin._nav')
    <form method="get" class="admin-search">
        <select name="level">
            <option value="">All levels</option>
            @foreach ($levels as $l)<option value="{{ $l }}" @selected($level === $l)>{{ ucfirst($l) }}</option>@endforeach
        </select>
        <input type="search" name="q" value="{{ $search }}" placeholder="Search messages">
        <button type="submit">Filter</button>
    </form>
    <p class="muted">Newest first, up to 200 entries from the end of storage/logs.</p>
    @forelse ($entries as $e)
        <details class="admin-log admin-log--{{ $e['level'] }}">
            <summary><span class="pill">{{ strtoupper($e['level']) }}</span> <span class="muted">{{ $e['at']->format('Y-m-d H:i:s') }}</span> {{ \Illuminate\Support\Str::limit($e['message'], 240) }}</summary>
            @if ($e['context'] !== '')<pre>{{ \Illuminate\Support\Str::limit($e['context'], 6000) }}</pre>@endif
        </details>
    @empty
        <p class="muted">No log entries match.</p>
    @endforelse
</section>
@endsection
