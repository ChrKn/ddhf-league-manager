<?php

namespace App\Http\Controllers;

use App\Models\Season;
use App\Models\Tournament;
use App\Support\SiteUrl;
use Illuminate\Http\Response;

/**
 * What a crawler is told about the public site: robots.txt and the sitemap.
 *
 * Both hang off the same switch as the X-Robots-Tag header - see HideFromSearchEngines. A sitemap
 * that offers pages the site at the same time asks to be kept out of the index is a contradiction a
 * search engine reports as an error, so while the site is not meant to be found there is none.
 *
 * robots.txt is a route rather than a file in public/ because the Sitemap line has to be an
 * absolute address, and that address is APP_URL - a file would carry one domain to every server.
 */
class SitemapController extends Controller
{
    /**
     * Every page of the site, once per language, each naming both of its versions.
     *
     * The search is not in it - findable on the site, not through a search engine - and neither is
     * any filtered address, which would only be the same page again. No lastmod: the newest
     * updated_at does not move when a result is deleted, and a date that is sometimes wrong is
     * worse than none. Only released seasons and their tournaments, through the same scopes the
     * pages use.
     */
    public function sitemap(): Response
    {
        abort_unless(config('site.indexable'), 404);

        $pages = [['index', []], ['standings', []], ['tournaments', []], ['news', []]];

        foreach (Season::public()->orderBy('id')->pluck('public_id') as $publicId) {
            $pages[] = ['standing', $publicId];
        }

        foreach (Tournament::public()->orderBy('tournaments.id')->pluck('public_id') as $publicId) {
            $pages[] = ['tournament', $publicId];
        }

        $entries = array_map(fn (array $page) => [
            'de' => SiteUrl::in($page[0], 'de', $page[1]),
            'en' => SiteUrl::in($page[0], 'en', $page[1]),
        ], $pages);

        // The declaration here rather than in the view: see the note at the top of sitemap.blade.php.
        // ltrim because nothing may stand before it, not even a line break.
        return response('<?xml version="1.0" encoding="UTF-8"?>' . "\n" . ltrim(view('site.sitemap', ['entries' => $entries])->render()))
            ->header('Content-Type', 'application/xml; charset=utf-8');
    }

    /**
     * Everybody in, always. `Disallow: /` would keep a crawler from reading the noindex as well -
     * see HideFromSearchEngines - so the switch only decides whether the sitemap is named.
     */
    public function robots(): Response
    {
        $lines = ['User-agent: *', 'Disallow:'];

        if (config('site.indexable')) {
            $lines[] = '';
            $lines[] = 'Sitemap: ' . route('sitemap');
        }

        return response(implode("\n", $lines) . "\n")
            ->header('Content-Type', 'text/plain; charset=utf-8');
    }
}
