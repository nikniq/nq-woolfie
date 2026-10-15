@extends('layouts.app')

@section('title', 'Room editor — sign in')

@section('content')
<section class="editor-login">
    <h2>Room editor</h2>
    <form method="post" action="{{ route('editor.authenticate') }}">
        @csrf
        <label class="field">Password
            <input type="password" name="password" autofocus autocomplete="current-password">
        </label>
        @error('password')<p class="error">{{ $message }}</p>@enderror
        <button type="submit">Enter</button>
    </form>
</section>
@endsection
