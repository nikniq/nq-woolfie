@extends('layouts.app')

@section('title', 'Room editor')

@section('content')
<section class="editor-index">
    <h2>Rooms of the castle</h2>
    <p class="muted">Rooms you save here are marked custom and are kept when the seeder runs. Delete a custom room to fall back to the seeded version on the next seed.</p>
    <table>
        <thead><tr><th>#</th><th>Name</th><th>Castle</th><th>Guards</th><th>Portals</th><th>Source</th><th></th></tr></thead>
        <tbody>
        @foreach ($levels as $level)
            <tr>
                <td>{{ $level->number }}</td>
                <td>{{ $level->name }}</td>
                <td>{{ $level->castle_x }},{{ $level->castle_y }}</td>
                <td>{{ count($level->guards ?? []) }}</td>
                <td>{{ implode(' ', array_map(fn ($p) => $p['id'].'→'.($p['to_level'] ?? '?'), $level->portals ?? [])) }}</td>
                <td>{{ $level->custom ? 'editor' : 'seeded' }}</td>
                <td>
                    <a href="{{ route('editor.edit', $level) }}">Edit</a> ·
                    <a href="{{ route('game', ['level' => $level->number]) }}">Play</a>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <p>
        <a class="button" href="{{ route('editor.create') }}">New room</a>
        <form method="post" action="{{ route('editor.logout') }}" style="display:inline">@csrf <button type="submit">Sign out</button></form>
    </p>
</section>
@endsection
