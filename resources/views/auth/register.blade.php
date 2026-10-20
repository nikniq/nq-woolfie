@extends('layouts.app')

@section('title', 'Create an account — '.config('app.name'))

@section('content')
<section class="auth-form">
    <h2>Create an account</h2>
    <p class="muted">Your player name goes on the leaderboard, and your campaign is saved to your account so you can continue on any device.</p>
    <form method="post" action="{{ route('register') }}">
        @csrf
        <label class="field">Player name
            <input type="text" name="name" value="{{ old('name') }}" maxlength="24" required autofocus autocomplete="nickname">
        </label>
        @error('name')<p class="error">{{ $message }}</p>@enderror
        <label class="field">Email
            <input type="email" name="email" value="{{ old('email') }}" required autocomplete="email">
        </label>
        @error('email')<p class="error">{{ $message }}</p>@enderror
        <label class="field">Password
            <input type="password" name="password" required autocomplete="new-password">
        </label>
        @error('password')<p class="error">{{ $message }}</p>@enderror
        <label class="field">Confirm password
            <input type="password" name="password_confirmation" required autocomplete="new-password">
        </label>
        <button type="submit">Register</button>
        <p class="muted">Already have an account? <a href="{{ route('login') }}">Sign in</a>.</p>
    </form>
</section>
@endsection
