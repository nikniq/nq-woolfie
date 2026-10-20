@extends('layouts.app')

@section('title', 'Sign in — '.config('app.name'))

@section('content')
<section class="auth-form">
    <h2>Sign in</h2>
    <form method="post" action="{{ route('login') }}">
        @csrf
        <label class="field">Email
            <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email">
        </label>
        @error('email')<p class="error">{{ $message }}</p>@enderror
        <label class="field">Password
            <input type="password" name="password" required autocomplete="current-password">
        </label>
        <label class="check"><input type="checkbox" name="remember" value="1" checked> Stay signed in</label>
        <button type="submit">Sign in</button>
        <p class="muted">No account yet? <a href="{{ route('register') }}">Register</a>.</p>
    </form>
</section>
@endsection
