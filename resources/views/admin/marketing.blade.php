@extends('layouts.app')

@section('title', 'Marketing — Admin')

@section('content')
<section class="admin">
    <h2>Marketing <span class="muted">last 30 days</span></h2>
    @include('admin._nav')
    <div class="admin-stats">
        <div class="admin-stat"><strong>{{ number_format($totals['visits']) }}</strong><span>Visits</span></div>
        <div class="admin-stat"><strong>{{ number_format($totals['signups']) }}</strong><span>Signups</span></div>
        <div class="admin-stat"><strong>{{ $totals['visits'] ? round($totals['signups'] / $totals['visits'] * 100, 1) : 0 }}%</strong><span>Visit → signup</span></div>
    </div>

    <h3>Traffic sources</h3>
    <table>
        <thead><tr><th>Source</th><th>Visits</th><th>Signups</th><th>Conversion</th></tr></thead>
        <tbody>
        @forelse ($sources as $s)
            <tr><td>{{ $s['source'] }}</td><td>{{ $s['visits'] }}</td><td>{{ $s['signups'] }}</td><td>{{ $s['visits'] ? round($s['signups'] / $s['visits'] * 100, 1).'%' : '—' }}</td></tr>
        @empty
            <tr><td colspan="4" class="muted">No visits recorded yet.</td></tr>
        @endforelse
        </tbody>
    </table>

    <div class="admin-cols">
        <div>
            <h3>Campaigns</h3>
            <table>
                <thead><tr><th>Campaign</th><th>Source</th><th>Visits</th></tr></thead>
                <tbody>
                @forelse ($campaigns as $c)
                    <tr><td>{{ $c->campaign }}</td><td>{{ $c->source }}</td><td>{{ $c->visits }}</td></tr>
                @empty
                    <tr><td colspan="3" class="muted">No tagged campaigns yet. Build a link below.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div>
            <h3>Landing pages</h3>
            <table>
                <thead><tr><th>Page</th><th>Visits</th></tr></thead>
                <tbody>
                @forelse ($landings as $l)
                    <tr><td>{{ $l->landing }}</td><td>{{ $l->visits }}</td></tr>
                @empty
                    <tr><td colspan="2" class="muted">No visits yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <h3>Campaign link builder</h3>
    <form class="utm-builder" id="utmBuilder" onsubmit="return false">
        <label>Page <select name="path"><option value="/">Home</option><option value="/play">Play</option><option value="/leaderboard">Leaderboard</option><option value="/register">Register</option></select></label>
        <label>Source <input name="utm_source" placeholder="reddit, newsletter, discord" required></label>
        <label>Medium <input name="utm_medium" placeholder="social, email, cpc"></label>
        <label>Campaign <input name="utm_campaign" placeholder="launch-nov"></label>
    </form>
    <p class="utm-output"><input id="utmUrl" readonly value="{{ $baseUrl }}/"> <button type="button" id="utmCopy">Copy</button></p>

    <h3>Share kit</h3>
    <p class="muted">Links already tagged with a source, so visits and signups from each network show up above.</p>
    @php($tagged = fn (string $source) => urlencode($baseUrl.'/?utm_source='.$source.'&utm_medium=social&utm_campaign=share'))
    <p class="share-kit">
        <a class="btn btn--sm" target="_blank" rel="noopener" href="https://twitter.com/intent/tweet?text={{ urlencode($shareText) }}&url={{ $tagged('twitter') }}">Post on X</a>
        <a class="btn btn--sm" target="_blank" rel="noopener" href="https://www.facebook.com/sharer/sharer.php?u={{ $tagged('facebook') }}">Facebook</a>
        <a class="btn btn--sm" target="_blank" rel="noopener" href="https://www.reddit.com/submit?url={{ $tagged('reddit') }}&title={{ urlencode('Woolfie: a free browser stealth game') }}">Reddit</a>
        <a class="btn btn--sm" target="_blank" rel="noopener" href="https://www.linkedin.com/sharing/share-offsite/?url={{ $tagged('linkedin') }}">LinkedIn</a>
    </p>
    <p class="muted">Search engines: <a href="{{ route('sitemap') }}">sitemap.xml</a> and <a href="{{ route('robots') }}">robots.txt</a> are generated automatically; submit the sitemap in Google Search Console. Pages carry Open Graph and Twitter card tags so shared links show a preview.</p>
</section>
<script>
(() => {
    const form = document.getElementById('utmBuilder'), out = document.getElementById('utmUrl'), base = @json($baseUrl);
    const build = () => {
        const params = new URLSearchParams();
        for (const key of ['utm_source', 'utm_medium', 'utm_campaign']) { const v = form.elements[key].value.trim().toLowerCase().replace(/\s+/g, '-'); if (v) params.set(key, v); }
        out.value = base + form.elements.path.value + (params.toString() ? '?' + params : '');
    };
    form.addEventListener('input', build);
    document.getElementById('utmCopy').addEventListener('click', (e) => { navigator.clipboard?.writeText(out.value); e.target.textContent = 'Copied'; setTimeout(() => e.target.textContent = 'Copy', 1500); });
})();
</script>
@endsection
