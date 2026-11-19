@extends('layouts.app')

@section('title', 'Game runs — Admin')

@section('content')
<section class="admin">
    <h2>Game runs</h2>
    @include('admin._nav')
    <form method="get" class="admin-search">
        <input type="search" name="player" value="{{ $player }}" placeholder="Filter by player">
        <button type="submit">Filter</button>
    </form>
    @include('admin._runs-table', ['runs' => $runs, 'deletable' => true])
    {{ $runs->links('admin._pagination') }}
</section>
@endsection
