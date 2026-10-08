@extends('layouts.app')

@section('title', config('app.name').' — Play')

@section('content')
<div id="gameWrap"
     data-levels-url="{{ route('api.levels.index') }}"
     data-runs-url="{{ route('api.runs.store') }}">
    <canvas id="game" width="640" height="480"></canvas>
    <div id="hud">
        <div id="status">Use WASD / Arrow keys. Find the key, avoid guards.</div>
        <div id="controls">
            <div id="levelLabel">Level: <span id="level">1</span> / {{ $levelCount }}</div>
            <div id="timer">0.0s</div>
            <label><input type="checkbox" id="sound" checked> Sound</label>
        </div>
        <label class="field">Name
            <input type="text" id="playerName" maxlength="24" placeholder="Anonymous" autocomplete="nickname">
        </label>
        <div id="info"></div>
        <button id="restart" type="button">Restart</button>
    </div>
</div>
@endsection
