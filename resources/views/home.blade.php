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
        <svg viewBox="0 0 320 220" shape-rendering="crispEdges">
            <rect width="320" height="220" fill="#15131b"/>
            <g fill="#2a2732">
                <rect x="0" y="0" width="320" height="20"/><rect x="0" y="200" width="320" height="20"/>
                <rect x="0" y="0" width="20" height="220"/><rect x="300" y="0" width="20" height="220"/>
                <rect x="140" y="20" width="20" height="80"/><rect x="200" y="120" width="100" height="20"/>
            </g>
            <polygon points="230,70 150,30 150,150" fill="#ffe066" opacity=".16"/>
            <rect x="222" y="62" width="16" height="16" fill="#b33"/><rect x="226" y="54" width="8" height="8" fill="#e8c9a0"/>
            <rect x="44" y="150" width="16" height="16" fill="#3c8"/><rect x="48" y="142" width="8" height="8" fill="#e8c9a0"/>
            <rect x="80" y="40" width="20" height="20" fill="#7a5530"/><rect x="84" y="44" width="12" height="12" fill="#8f6538"/>
            <rect x="260" y="170" width="10" height="6" fill="#ffd34d"/><rect x="266" y="166" width="4" height="4" fill="#ffd34d"/>
            <rect x="40" y="40" width="8" height="8" fill="#ffdd00"/><rect x="44" y="44" width="8" height="8" fill="#ffdd00"/>
            <g fill="#4a4652"><rect x="260" y="36" width="24" height="4"/><rect x="260" y="44" width="24" height="4"/><rect x="260" y="52" width="24" height="4"/></g>
            <circle cx="230" cy="40" r="6" fill="#fff"/><circle cx="230" cy="40" r="3" fill="#c33"/>
        </svg>
        <div class="hero__badge">Room 1 of {{ $levels->count() }} · The Cellar</div>
    </div>
</section>

<section class="stats-strip">
    <div><strong>{{ $levels->count() }}</strong> <span>connected rooms</span></div>
    <div><strong>3</strong><span>difficulty modes</span></div>
    <div><strong>{{ number_format($stats['runs']) }}</strong><span>rooms cleared by players</span></div>
    <div><strong>{{ number_format($stats['escapes']) }}</strong><span>successful escapes</span></div>
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
