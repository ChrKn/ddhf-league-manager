<?php

use App\Http\Controllers\CronController;
use Illuminate\Support\Facades\Route;

/*
 * The address the hosting control panel fetches once a minute, and the only reason this file is
 * separate from the other two: it wants no middleware at all.
 *
 * Not the web group - a session row a minute is 1440 rows a day of nobody, and the scheduler has no
 * use for a cookie or a CSRF token. Not the api group either, because /api belongs to the
 * federation's key holders and this is the server talking to itself.
 *
 * A GET with side effects, which is not how it should be. The form fetches a URL; there is no
 * choice to make here.
 */
Route::get('/cron', CronController::class)->name('cron');
