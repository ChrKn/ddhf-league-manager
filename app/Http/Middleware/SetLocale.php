<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the language from the route the request came in on.
 *
 * The English pages are registered under names beginning "site.en.", which is the only place the
 * language is written down - there is no second source of truth to fall out of step with the
 * URL. German has no prefix, so its addresses are unchanged from before there was a second
 * language.
 */
class SetLocale
{
    /** The languages the site is written in. Anything else falls back to German. */
    public const SUPPORTED = ['de', 'en'];

    /** The one that has no prefix. */
    public const DEFAULT = 'de';

    public function handle(Request $request, Closure $next): Response
    {
        $name = $request->route()?->getName() ?? '';

        App::setLocale(str_starts_with($name, 'site.en.') ? 'en' : self::DEFAULT);

        return $next($request);
    }
}
