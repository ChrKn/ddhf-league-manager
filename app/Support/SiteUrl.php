<?php

namespace App\Support;

use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;

/**
 * Addresses a page of the public site in whichever language it is being read in.
 *
 * The site is registered twice: once without a prefix for German, once under /en for English.
 * German keeps the plain route names it always had, so nothing outside this class had to be
 * renamed; English adds "en." in the middle. Everything that links to a page asks here for the
 * address rather than naming a route, which is what keeps a reader inside one language.
 *
 * The obvious alternative - one route set with an optional {locale} segment - does not work:
 * an optional prefix leaves "/ranglisten" unmatched and hangs "?locale=" on every German URL.
 */
final class SiteUrl
{
    /**
     * The path each page sits under, per language.
     *
     * Here rather than in lang/, because routing must not depend on a translation being loadable
     * at boot, and because these are addresses first and words second - changing one breaks
     * every link anybody saved, which is a heavier decision than rewording a heading.
     *
     * The German asymmetry is deliberate and carried over: standings answer at /ranglisten,
     * tournaments at /ddhf-turniere, because "Turniere" alone would claim more than this site
     * shows - only the ones that count towards a DDHF standing are here.
     */
    public const SLUGS = [
        'de' => ['standings' => 'ranglisten', 'tournaments' => 'ddhf-turniere', 'search' => 'suche'],
        'en' => ['standings' => 'standings',  'tournaments' => 'ddhf-tournaments', 'search' => 'search'],
    ];

    public static function to(string $name, mixed $parameters = []): string
    {
        return route(self::routeName($name, App::getLocale()), $parameters);
    }

    /**
     * The page currently being shown, in the other language.
     *
     * Falls back to that language's front page when the current route has no counterpart, which
     * is what a 404 or an admin page would hit.
     */
    public static function alternate(?string $locale = null): string
    {
        $locale ??= self::other();
        $short = self::currentShortName();

        if ($short === null) {
            return route(self::routeName('index', $locale));
        }

        $parameters = Route::current()?->parameters() ?? [];
        $query = request()->query();

        return route(self::routeName($short, $locale), $parameters + $query);
    }

    /**
     * The current page without its language: "standings" for both site.standings and
     * site.en.standings. Null when the request is not on a site route at all.
     */
    public static function currentShortName(): ?string
    {
        $current = Route::currentRouteName();

        if ($current === null || !str_starts_with($current, 'site.')) {
            return null;
        }

        return str_starts_with($current, 'site.en.')
            ? substr($current, strlen('site.en.'))
            : substr($current, strlen('site.'));
    }

    /** The language the reader is not in. */
    public static function other(): string
    {
        return App::getLocale() === SetLocale::DEFAULT ? 'en' : SetLocale::DEFAULT;
    }

    private static function routeName(string $short, string $locale): string
    {
        return $locale === SetLocale::DEFAULT ? "site.{$short}" : "site.{$locale}.{$short}";
    }
}
