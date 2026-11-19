<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class SeoController extends Controller
{
    public function sitemap(): Response
    {
        $urls = collect(['home' => ['1.0', 'weekly'], 'game' => ['0.9', 'weekly'], 'leaderboard' => ['0.7', 'daily'], 'register' => ['0.5', 'monthly']])
            ->map(fn (array $meta, string $route): string => sprintf('<url><loc>%s</loc><changefreq>%s</changefreq><priority>%s</priority></url>', e(route($route)), $meta[1], $meta[0]))
            ->implode('');

        return response('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.$urls.'</urlset>', 200, ['Content-Type' => 'application/xml']);
    }

    public function robots(): Response
    {
        return response("User-agent: *\nDisallow: /admin\nDisallow: /editor\nDisallow: /save\n\nSitemap: ".route('sitemap')."\n", 200, ['Content-Type' => 'text/plain']);
    }
}
