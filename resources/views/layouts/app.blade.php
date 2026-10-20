<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=VT323&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <header class="site-header">
        <h1><a href="{{ route('game') }}">{{ config('app.name') }}</a></h1>
        <nav>
            <a href="{{ route('game') }}" @class(['active' => request()->routeIs('game')])>Play</a>
            <a href="{{ route('leaderboard') }}" @class(['active' => request()->routeIs('leaderboard')])>Leaderboard</a>
            @if (config('game.editor_password') !== '')
                <a href="{{ route('editor.index') }}" @class(['active' => request()->routeIs('editor.*')])>Editor</a>
            @endif
        </nav>
        <div class="account">
            @auth
                <a href="{{ route('player', auth()->user()->name) }}">{{ auth()->user()->name }}</a>
                <form method="post" action="{{ route('logout') }}" class="inline">@csrf <button type="submit" class="link">Sign out</button></form>
            @else
                <a href="{{ route('login') }}" @class(['active' => request()->routeIs('login')])>Sign in</a>
                <a href="{{ route('register') }}" @class(['active' => request()->routeIs('register')])>Register</a>
            @endauth
        </div>
    </header>
    <main>
        @if (session('status'))
            <p class="flash">{{ session('status') }}</p>
        @endif
        @yield('content')
    </main>
</body>
</html>
