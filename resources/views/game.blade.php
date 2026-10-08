@extends('layouts.app')

@section('title', config('app.name').' — Play')

@section('content')
<div id="gameWrap"
     data-levels-url="{{ route('api.levels.index') }}"
     data-runs-url="{{ route('api.runs.store') }}"
     data-sounds='@json($sounds)'>
    <div id="stage">
        <canvas id="game" width="640" height="320"></canvas>
        <div id="touch" aria-label="Touch controls">
            <button type="button" data-dir="up" aria-label="Up">▲</button>
            <button type="button" data-dir="left" aria-label="Left">◀</button>
            <button type="button" data-dir="right" aria-label="Right">▶</button>
            <button type="button" data-dir="down" aria-label="Down">▼</button>
        </div>
    </div>
    <div id="hud">
        <div id="status">Arrows / WASD move, hold Shift to sprint (noisy). Stand on crates to hide. Key opens doors, gold is points. P pauses, R restarts. Gamepad works too.</div>
        <div id="controls">
            <div id="levelLabel">Level <span id="level">1</span> / {{ $levelCount }}</div>
            <div id="timer">0.0s</div>
        </div>
        <div id="controls">
            <div>Score <strong id="score">0</strong></div>
            <div>Lives <span id="lives">♥♥♥</span></div>
            <label><input type="checkbox" id="sound" checked> Sound</label>
        </div>
        <label class="field">Name
            <input type="text" id="playerName" maxlength="24" placeholder="Anonymous" autocomplete="nickname">
        </label>
        <div id="castleWrap">
            <div class="label">Castle map <span class="muted">(M for full view)</span></div>
            <canvas id="castle" width="260" height="190"></canvas>
        </div>
        <div id="info"></div>
        <button id="restart" type="button">Restart level</button>
    </div>
</div>
@endsection
