<?php

use App\Http\Controllers\SiteController;
use App\Http\Middleware\SetLocale;
use App\Support\SiteUrl;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/*
 * The site the federation's readers see. Standings and the tournaments they are made of, and
 * nothing else - no federations, clubs, fencers, events or seasons in their own right, and no
 * link into the admin panel.
 *
 * One definition, registered once per language: German at the root with German paths, English
 * under /en with English ones. The route names differ only by "en." in the middle, so German
 * keeps the names it always had and SiteUrl can turn a short name into whichever language a page
 * is being rendered in.
 *
 * Each language has exactly one address for a page - /ranglisten in German, /en/standings in
 * English - so nothing answers twice in the same language and there is nothing for a search
 * engine to tell apart.
 */
$site = function (string $locale) {
    $slug = SiteUrl::SLUGS[$locale];

    Route::get('/', [SiteController::class, 'index'])->name('index');
    Route::get("/{$slug['standings']}", [SiteController::class, 'standings'])->name('standings');
    Route::get("/{$slug['standings']}/{public_id}", [SiteController::class, 'standing'])->name('standing');
    Route::get("/{$slug['tournaments']}", [SiteController::class, 'tournaments'])->name('tournaments');
    Route::get("/{$slug['tournaments']}/{public_id}", [SiteController::class, 'tournament'])->name('tournament');
    Route::get("/{$slug['search']}", [SiteController::class, 'search'])->name('search');
};

/*
 * Nothing a reader does here needs a session: every page is a GET, the only forms are filters and
 * the search box, and nobody logs in. With the web group's session left on, every visit wrote a
 * row with the visitor's IP address and browser into the sessions table and handed back two
 * cookies for it. Without it, the site sets no cookie at all.
 */
$stateless = [StartSession::class, ShareErrorsFromSession::class, PreventRequestForgery::class];

Route::middleware(SetLocale::class)
    ->withoutMiddleware($stateless)
    ->name('site.')
    ->group(fn () => $site('de'));

Route::prefix('en')
    ->middleware(SetLocale::class)
    ->withoutMiddleware($stateless)
    ->name('site.en.')
    ->group(fn () => $site('en'));

/*
 * The data browser used to live here, at /tests, with no middleware at all - a page showing
 * every column the public site withholds, answering to anybody who knew the address. It is an
 * admin's view, so it is now part of the admin panel: see App\Filament\Pages\Browse.
 */
