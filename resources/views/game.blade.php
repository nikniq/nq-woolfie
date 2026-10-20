@extends('layouts.app')

@section('title', config('app.name').' — Play')

@section('content')
<div id="gameWrap"
     data-levels-url="{{ route('api.levels.index') }}"
     data-runs-url="{{ route('api.runs.store') }}"
     data-campaigns-url="{{ route('api.campaigns.store') }}"
     data-daily-url="{{ route('api.daily.show') }}"
     @auth data-user-name="{{ auth()->user()->name }}" data-save-url="{{ route('save.show') }}" @endauth
     data-sounds='@json($sounds)'>
    <div id="stage">
        <canvas id="game" width="640" height="320"></canvas>
        <div id="touch" aria-label="Touch controls">
            <button type="button" data-dir="up" aria-label="Up">▲</button>
            <button type="button" data-dir="left" aria-label="Left">◀</button>
            <button type="button" data-dir="right" aria-label="Right">▶</button>
            <button type="button" data-dir="down" aria-label="Down">▼</button>
            <button type="button" data-action="fire" aria-label="Fire">●</button>
            <button type="button" data-action="stone" aria-label="Throw stone">◌</button>
            <button type="button" data-action="use" aria-label="Take uniform">E</button>
            <button type="button" data-action="grenade" aria-label="Throw grenade">✸</button>
        </div>
    </div>
    <div id="hud">
        <div id="status">Arrows / WASD move, Shift sprints (noisy), F fires, Q throws a stone, G a grenade, E takes a uniform from a body or carries it. Crates hide you. P pauses, M map, R restarts. Gamepad works too.</div>
        <div id="controls">
            <div id="levelLabel">Level <span id="level">1</span> / {{ $levelCount }}</div>
            <div id="timer">0.0s</div>
        </div>
        <div id="controls">
            <div>Score <strong id="score">0</strong></div>
            <div>Lives <span id="lives">♥♥♥</span></div>
            <label><input type="checkbox" id="sound" checked> SFX</label>
            <label id="music"><input type="checkbox" id="musicToggle" checked> Music</label>
        </div>
        @auth
            <label class="field">Playing as
                <input type="text" id="playerName" value="{{ auth()->user()->name }}" readonly>
            </label>
        @else
            <label class="field">Name <a class="muted small" href="{{ route('register') }}">(register to save progress)</a>
                <input type="text" id="playerName" maxlength="24" placeholder="Anonymous" autocomplete="nickname">
            </label>
        @endauth
        <div id="objectives"></div>
        <div id="weapon">Unarmed</div>
        <div id="castleWrap">
            <div class="label">Castle map <span class="muted">(M for full view)</span></div>
            <canvas id="castle" width="260" height="190"></canvas>
        </div>
        <div id="info"></div>
        <button id="restart" type="button">Restart level</button>
    </div>
</div>
@endsection
