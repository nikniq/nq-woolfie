@extends('layouts.app')

@section('title', 'Admin — Woolfie')

@section('content')
<section class="admin">
    <h2>Admin</h2>
    @include('admin._nav')
    <div class="admin-stats">
        @foreach ($stats as $label => $value)
            <div class="admin-stat"><strong>{{ number_format($value) }}</strong><span>{{ $label }}</span></div>
        @endforeach
    </div>

    <h3>Newest users</h3>
    <table>
        <thead><tr><th>Name</th><th>Email</th><th>Joined</th></tr></thead>
        <tbody>
        @forelse ($recentUsers as $user)
            <tr><td>{{ $user->name }}</td><td>{{ $user->email }}</td><td>{{ $user->created_at?->diffForHumans() }}</td></tr>
        @empty
            <tr><td colspan="3" class="muted">No users yet.</td></tr>
        @endforelse
        </tbody>
    </table>

    <h3>Latest runs</h3>
    @include('admin._runs-table', ['runs' => $recentRuns, 'deletable' => false])
</section>
@endsection
