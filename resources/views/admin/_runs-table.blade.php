<table>
    <thead><tr><th>When</th><th>Player</th><th>Room</th><th>Outcome</th><th>Score</th><th>Time</th><th>Difficulty</th>@if ($deletable)<th></th>@endif</tr></thead>
    <tbody>
    @forelse ($runs as $run)
        <tr>
            <td>{{ $run->created_at?->format('Y-m-d H:i') }}</td>
            <td><a href="{{ route('player', $run->player_name) }}">{{ $run->player_name }}</a></td>
            <td>{{ $run->level ? $run->level->number.'. '.$run->level->name : '—' }}</td>
            <td>{{ $run->outcome }}</td>
            <td>{{ $run->score }}</td>
            <td>{{ number_format($run->time_ms / 1000, 1) }}s</td>
            <td>{{ $run->difficulty }}</td>
            @if ($deletable)
                <td>
                    <form method="post" action="{{ route('admin.runs.destroy', $run) }}" class="inline" onsubmit="return confirm('Delete this run?')">
                        @csrf @method('DELETE') <button type="submit" class="link">Delete</button>
                    </form>
                </td>
            @endif
        </tr>
    @empty
        <tr><td colspan="8" class="muted">No runs.</td></tr>
    @endforelse
    </tbody>
</table>
