<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps the whole site out of search results while it is not meant to be found yet.
 *
 * A header rather than a line in robots.txt, and that is the point of this class rather than a
 * detail of it. `Disallow: /` tells a crawler not to *fetch* the page - which means it never sees
 * a noindex either, and an address it learned about from somewhere else can still end up in the
 * results, listed without a description. Telling it to come in and then not to keep anything is
 * the instruction that actually works, so robots.txt stays open on purpose.
 *
 * Global rather than on the site's routes: /api and the panel have no business being indexed
 * either, and a page that gets a header it does not need loses nothing by it.
 */
class HideFromSearchEngines
{
    /**
     * Handle an incoming request.
     *
     * @param Closure(Request): (Response) $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (!config('site.indexable')) {
            // follow, matching the robots meta in the site's layout. A crawler takes the stricter
            // of two answers that disagree, and the pages it would reach by following a link say
            // noindex themselves.
            $response->headers->set('X-Robots-Tag', 'noindex, follow');
        }

        return $response;
    }
}
