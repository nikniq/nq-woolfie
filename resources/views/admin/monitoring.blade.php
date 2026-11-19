@extends('layouts.app')

@section('title', 'Monitoring — Admin')

@section('content')
<section class="admin">
    <h2>Monitoring <span class="muted">last 30 days</span></h2>
    @include('admin._nav')
    <div class="admin-charts">
        @foreach ($series as $title => $data)
            @include('admin._bars', ['title' => $title, 'data' => $data, 'tone' => $title === 'Errors' ? 'bad' : null])
        @endforeach
    </div>
    <h3>Latest errors</h3>
    @forelse ($recentErrors as $e)
        <p class="admin-log admin-log--{{ $e['level'] }}"><span class="muted">{{ $e['at']->format('Y-m-d H:i') }}</span> {{ \Illuminate\Support\Str::limit($e['message'], 200) }}</p>
    @empty
        <p class="muted">No errors logged.</p>
    @endforelse
    <p><a href="{{ route('admin.logs', ['level' => 'error']) }}">All errors →</a></p>
</section>
@endsection
