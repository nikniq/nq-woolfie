<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="@yield('description', 'Woolfie is a top-down stealth game in your browser: sneak through twelve castle rooms, steal the war plans and escape.')">
    <title>@yield('title', 'Woolfie')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700&family=Inter:wght@400;500;600&family=VT323&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body @class(['page-'.str_replace('.', '-', request()->route()?->getName() ?? 'page')])>
    <header class="site-header">
        <div class="site-header__inner">
            <a class="brand" href="{{ route('home') }}" aria-label="Woolfie home">
                <span class="brand__mark" aria-hidden="true">W</span>
                <span class="brand__name">Woolfie</span>
            </a>
            <input type="checkbox" id="navToggle" class="nav-toggle" aria-label="Toggle menu">
            <label for="navToggle" class="nav-burger" aria-hidden="true"><span></span><span></span><span></span></label>
            <div class="nav-panel">
                <nav class="nav-main" aria-label="Main">
                    <a href="{{ route('home') }}#features">Features</a>
                    <a href="{{ route('home') }}#how">How it works</a>
                    <a href="{{ route('leaderboard') }}" @class(['active' => request()->routeIs('leaderboard')])>Leaderboard</a>
                    @if (config('game.editor_password') !== '')
                        <a href="{{ route('editor.index') }}" @class(['active' => request()->routeIs('editor.*')])>Editor</a>
                    @endif
                    <a href="https://nikniq.com" target="_blank" rel="noopener">NikNiq ↗</a>
                </nav>
                <div class="nav-account">
                    @auth
                        <a href="{{ route('player', auth()->user()->name) }}">{{ auth()->user()->name }}</a>
                        <form method="post" action="{{ route('logout') }}" class="inline">@csrf <button type="submit" class="link">Sign out</button></form>
                    @else
                        <a href="{{ route('login') }}" @class(['active' => request()->routeIs('login')])>Sign in</a>
                    @endauth
                    <a href="{{ route('game') }}" @class(['btn', 'btn--primary', 'btn--sm', 'active' => request()->routeIs('game')])>Play now</a>
                </div>
            </div>
        </div>
    </header>
    <main>
        @if (session('status'))
            <p class="flash">{{ session('status') }}</p>
        @endif
        @yield('content')
    </main>
    <footer class="site-footer">
        <div class="site-footer__inner">
            <div class="footer-brand">
                <a class="brand" href="{{ route('home') }}"><span class="brand__mark" aria-hidden="true">W</span><span class="brand__name">Woolfie</span></a>
                <p>A stealth adventure in twelve rooms. Free to play in any modern browser, on desktop, mobile or gamepad.</p>
            </div>
            <div class="footer-col">
                <h4>Game</h4>
                <a href="https://nikniq.com" target="_blank" rel="noopener">More from NikNiq ↗</a>
                <a href="{{ route('game') }}">Play now</a>
                <a href="{{ route('home') }}#features">Features</a>
                <a href="{{ route('home') }}#how">How it works</a>
                <a href="{{ route('leaderboard') }}">Leaderboard</a>
            </div>
            <div class="footer-col">
                <h4>Account</h4>
                @auth
                    <a href="{{ route('player', auth()->user()->name) }}">My profile</a>
                @else
                    <a href="{{ route('register') }}">Create account</a>
                    <a href="{{ route('login') }}">Sign in</a>
                @endauth
            </div>
        </div>
        <div class="site-footer__bottom">
            <span>&copy; {{ date('Y') }} Woolfie. All rights reserved. A game by <a href="https://nikniq.com" target="_blank" rel="noopener">NikNiq</a>.</span>
            <a href="#top" onclick="window.scrollTo({top:0,behavior:'smooth'});return false;">Back to top ↑</a>
        </div>
    </footer>
</body>
</html>
