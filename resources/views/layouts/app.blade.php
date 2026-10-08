<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <header class="site-header">
        <h1><a href="{{ route('game') }}">{{ config('app.name') }}</a></h1>
        <nav>
            <a href="{{ route('game') }}" @class(['active' => request()->routeIs('game')])>Play</a>
            <a href="{{ route('leaderboard') }}" @class(['active' => request()->routeIs('leaderboard')])>Leaderboard</a>
        </nav>
    </header>
    <main>
        @yield('content')
    </main>
</body>
</html>
