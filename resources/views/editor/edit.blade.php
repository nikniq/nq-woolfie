@extends('layouts.app')

@section('title', ($isNew ? 'New room' : 'Edit '.$level->name).' — editor')

@section('content')
<section id="editor"
    data-level='@json($editorData)'
    data-others='@json($others)'
    data-save-url="{{ $isNew ? route('editor.store') : route('editor.update', $level) }}"
    data-method="{{ $isNew ? 'POST' : 'PUT' }}"
    data-delete-url="{{ $isNew ? '' : route('editor.destroy', $level) }}"
    data-index-url="{{ route('editor.index') }}"
    data-play-url="{{ route('game') }}">
    <div class="editor-top">
        <a href="{{ route('editor.index') }}">← All rooms</a>
        <label>Number <input type="number" id="edNumber" min="1" max="99" value="{{ $level->number }}"></label>
        <label>Name <input type="text" id="edName" maxlength="40" value="{{ $level->name }}"></label>
        <label>Castle column <input type="number" id="edCastleX" min="0" max="9" value="{{ $level->castle_x }}"></label>
        <label>row <input type="number" id="edCastleY" min="0" max="9" value="{{ $level->castle_y }}"></label>
        <label>Width <input type="number" id="edWidth" min="5" max="40" value="{{ count($level->map[0]) }}"></label>
        <label>Height <input type="number" id="edHeight" min="5" max="30" value="{{ count($level->map) }}"></label>
        <button type="button" id="edResize">Resize</button>
    </div>
    <div class="editor-body">
        <div class="editor-palette">
            <h3>Tiles</h3>
            <div id="edTiles"></div>
            <h3>Tools</h3>
            <div id="edTools"></div>
            <h3>Guards</h3>
            <div id="edGuards"></div>
            <button type="button" id="edAddGuard">Add guard</button>
            <h3>Blades</h3>
            <div id="edBlades"></div>
            <button type="button" id="edAddBlade">Add blade</button>
        </div>
        <div class="editor-stage">
            <canvas id="edCanvas"></canvas>
            <p class="muted" id="edHint">Left-click or drag to paint the selected tile. Right-click paints floor. With a guard selected, click tiles to add patrol points (straight legs only).</p>
            <div id="edErrors" class="errors" hidden></div>
            <div class="editor-actions">
                <button type="button" id="edSave">Save room</button>
                <button type="button" id="edPlay" @if($isNew) disabled @endif>Save &amp; play</button>
                <button type="button" id="edExport">Copy JSON</button>
                <button type="button" id="edImport">Paste JSON</button>
                @unless($isNew)<button type="button" id="edDelete" class="danger">Delete room</button>@endunless
            </div>
            <p class="muted" id="edStatus"></p>
        </div>
    </div>
</section>
@endsection
