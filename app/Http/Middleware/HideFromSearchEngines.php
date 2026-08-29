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
            // nofollow as well, so the links on a page that is not to be kept do not become the
            // way the next one is found.
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        }

        return $response;
    }
}
