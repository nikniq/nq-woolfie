@extends('layouts.app')

@section('title', 'Users — Admin')

@section('content')
<section class="admin">
    <h2>Users</h2>
    @include('admin._nav')
    <form method="get" class="admin-search">
        <input type="search" name="q" value="{{ $search }}" placeholder="Search name or email">
        <button type="submit">Search</button>
    </form>
    <table>
        <thead><tr><th>Name</th><th>Email</th><th>Sign-in</th><th>Runs</th><th>Joined</th><th>Role</th><th></th></tr></thead>
        <tbody>
        @forelse ($users as $user)
            <tr>
                <td><a href="{{ route('player', $user->name) }}">{{ $user->name }}</a></td>
                <td>{{ $user->email }}</td>
                <td>{{ collect(['Google' => $user->google_id, 'NikNiq' => $user->nikniq_id])->filter()->keys()->join(', ') ?: 'Password' }}</td>
                <td>{{ $user->game_runs_count }}</td>
                <td>{{ $user->created_at?->format('Y-m-d') }}</td>
                <td>{{ $user->is_admin ? 'Admin' : 'Player' }}</td>
                <td>
                    @unless ($user->is(auth()->user()))
                        <form method="post" action="{{ route('admin.users.toggle-admin', $user) }}" class="inline">
                            @csrf @method('PATCH') <button type="submit" class="link">{{ $user->is_admin ? 'Revoke admin' : 'Make admin' }}</button>
                        </form>
                        ·
                        <form method="post" action="{{ route('admin.users.destroy', $user) }}" class="inline" onsubmit="return confirm('Delete {{ $user->name }}? Their runs are kept but unlinked.')">
                            @csrf @method('DELETE') <button type="submit" class="link">Delete</button>
                        </form>
                    @endunless
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="muted">No users found.</td></tr>
        @endforelse
        </tbody>
    </table>
    {{ $users->links('admin._pagination') }}
</section>
@endsection
