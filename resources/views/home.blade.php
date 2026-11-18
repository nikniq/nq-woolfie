@extends('layouts.app')

@section('title', 'Woolfie — Sneak in. Steal the plans. Get out.')

@section('content')
<section class="hero">
    <div class="hero__copy">
        <p class="eyebrow">Free browser stealth game</p>
        <h1>Sneak in.<br>Steal the plans.<br><span>Get out alive.</span></h1>
        <p class="lead">Woolfie drops you at the gate of a guarded castle. Slip past patrols, pick locks, knock out sentries and wear their uniforms through {{ $levels->count() }} hand-built rooms, all the way to the War Room and back.</p>
        <div class="hero__cta">
            <a class="btn btn--primary btn--lg" href="{{ route('game') }}">Play free now</a>
            @guest
                <a class="btn btn--ghost btn--lg" href="{{ route('register') }}">Create free account</a>
            @else
                <a class="btn btn--ghost btn--lg" href="{{ route('leaderboard') }}">See the leaderboard</a>
            @endguest
        </div>
        <p class="hero__note">No download, no install. Works with keyboard, touch and gamepad.</p>
    </div>
    <div class="hero__art" aria-hidden="true">
        <img src="{{ asset('images/screens/room-5.jpg') }}" width="1326" height="570" alt="">
        <div class="hero__badge">In-game · {{ $levels[4] ?? 'The Keep' }}</div>
    </div>
</section>

<section class="stats-strip">
    <div><strong>{{ $levels->count() }}</strong> <span>connected rooms</span></div>
    <div><strong>3</strong><span>difficulty modes</span></div>
    @if ($stats['runs'] >= 100)
        <div><strong>{{ number_format($stats['runs']) }}</strong><span>rooms cleared by players</span></div>
    @else
        <div><strong>Free</strong><span>play in your browser</span></div>
    @endif
    @if ($stats['escapes'] >= 10)
        <div><strong>{{ number_format($stats['escapes']) }}</strong><span>successful escapes</span></div>
    @else
        <div><strong>Daily</strong><span>new challenge every day</span></div>
    @endif
</section>

<section class="section" aria-labelledby="screensTitle">
    <header class="section__head">
        <p class="eyebrow">Screenshots</p>
        <h2 id="screensTitle">Straight from the game</h2>
        <p>Torchlit corridors, sweeping searchlights and guards who notice. Every room is hand-built.</p>
    </header>
    <div class="screens">
        @foreach ([1, 10, 12] as $room)
            <figure>
                <img src="{{ asset("images/screens/room-{$room}.jpg") }}" width="1326" height="570" loading="lazy" alt="Gameplay in {{ $levels[$room - 1] ?? "room {$room}" }}">
                <figcaption>Room {{ $room }} · {{ $levels[$room - 1] ?? '' }}</figcaption>
            </figure>
        @endforeach
    </div>
</section>

<section id="features" class="section">
    <header class="section__head">
        <p class="eyebrow">Features</p>
        <h2>Every room is a puzzle with guards in it</h2>
        <p>Short to learn, hard to master. Plan a route, watch the patrols, and choose how loud you want to be.</p>
    </header>
    <div class="features">
        <article class="feature"><span class="feature__icon">👁</span><h3>Real stealth</h3><p>Guards see in cones of light and hear you sprint. Hide on crates, throw stones to lure them away, or slip past in the dark.</p></article>
        <article class="feature"><span class="feature__icon">🎭</span><h3>Disguises</h3><p>Knock out a guard from behind and take his uniform. Officers may still ask for your papers, so keep your nerve.</p></article>
        <article class="feature"><span class="feature__icon">🏰</span><h3>One connected castle</h3><p>Twelve rooms joined by stairs and archways, from the Cellar to the War Room, with a full castle map to plan your route.</p></article>
        <article class="feature"><span class="feature__icon">🏆</span><h3>Leaderboards &amp; records</h3><p>Personal bests and castle records for every room, a daily challenge, and achievements to show off on your profile.</p></article>
        <article class="feature"><span class="feature__icon">💾</span><h3>Cloud saves</h3><p>Create a free account and your campaign follows you between devices. Pick up exactly where you left off.</p></article>
        <article class="feature"><span class="feature__icon">🎮</span><h3>Play anywhere</h3><p>Keyboard, on-screen touch controls or a gamepad. Easy mode gives extra lives and guards who never shoot.</p></article>
    </div>
</section>

<section id="how" class="section section--alt">
    <header class="section__head">
        <p class="eyebrow">How it works</p>
        <h2>Your mission in four steps</h2>
    </header>
    <ol class="steps">
        <li><span>1</span><h3>Find the key</h3><p>Each room hides a key. The guide arrow points the way if you need it.</p></li>
        <li><span>2</span><h3>Avoid the light</h3><p>Stay out of the guards' sight. The eye above a guard warns you before he raises the alarm.</p></li>
        <li><span>3</span><h3>Take the stairs</h3><p>Unlock the doors, loot the gold and descend deeper into the castle.</p></li>
        <li><span>4</span><h3>Escape</h3><p>Grab the war plans in the War Room, then make it back to the gate you came in by.</p></li>
    </ol>
</section>

@if ($levels->isNotEmpty())
<section class="section">
    <header class="section__head">
        <p class="eyebrow">The castle</p>
        <h2>{{ $levels->count() }} rooms between you and the plans</h2>
    </header>
    <ul class="rooms">
        @foreach ($levels as $name)
            <li><span>{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>{{ $name }}</li>
        @endforeach
    </ul>
</section>
@endif

<section class="cta-band">
    <h2>Ready to break in?</h2>
    <p>It takes ten seconds to start. Escaping takes a little longer.</p>
    <a class="btn btn--primary btn--lg" href="{{ route('game') }}">Start your mission</a>
</section>
@endsection
