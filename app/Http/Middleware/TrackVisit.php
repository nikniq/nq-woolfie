<?php

namespace App\Http\Middleware;

use App\Models\Visit;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records the first page of each visit and where it came from (UTM tags or referrer),
 * keeping it in the session so a later signup can be attributed to it.
 */
class TrackVisit
{
    private const BOTS = '/bot|crawl|spider|slurp|preview|facebookexternalhit|curl|wget|python|headless|monitor/i';

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') && ! $request->expectsJson() && $request->hasSession()
            && ! $request->session()->has('attribution') && ! preg_match(self::BOTS, (string) $request->userAgent())) {
            $attribution = $this->attributionFor($request);
            $request->session()->put('attribution', $attribution);
            Visit::create($attribution);
        }

        return $next($request);
    }

    /** @return array{source: string, medium: ?string, campaign: ?string, landing: string} */
    private function attributionFor(Request $request): array
    {
        $referrerHost = parse_url((string) $request->headers->get('referer'), PHP_URL_HOST);
        $external = $referrerHost && $referrerHost !== $request->getHost();
        $source = $request->query('utm_source') ?: ($request->query('ref') ?: ($external ? Str::after($referrerHost, 'www.') : 'direct'));

        return [
            'source' => Str::limit(Str::lower((string) $source), 100, ''),
            'medium' => $request->query('utm_medium') ? Str::limit(Str::lower((string) $request->query('utm_medium')), 100, '') : ($external ? 'referral' : null),
            'campaign' => $request->query('utm_campaign') ? Str::limit((string) $request->query('utm_campaign'), 100, '') : null,
            'landing' => Str::limit('/'.ltrim($request->path(), '/'), 255, ''),
        ];
    }
}
