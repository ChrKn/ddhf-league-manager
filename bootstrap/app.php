<?php

use App\Http\Middleware\AuthenticateApiKey;
use App\Http\Middleware\HideFromSearchEngines;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // The scheduler's own door, with no middleware group behind it at all - see routes/cron.php
        // for why it is neither web nor api.
        then: function () {
            Route::group([], base_path('routes/cron.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Every response, not just the site's: while this is switched on there is nothing here
        // that should turn up in a search result.
        $middleware->append(HideFromSearchEngines::class);

        $middleware->alias([
            'auth.api' => AuthenticateApiKey::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
