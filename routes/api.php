<?php

use App\Http\Controllers\Api\FederationController;
use App\Http\Controllers\Api\FencerController;
use App\Http\Controllers\Api\GroupController;
use App\Http\Controllers\Api\SeasonController;
use App\Http\Controllers\Api\StandingController;
use App\Http\Controllers\Api\TournamentController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.api')->group(function () {
    Route::get('/groups', [GroupController::class, 'index']);
    Route::get('/groups/{public_id}', [GroupController::class, 'show']);

    Route::get('/federations', [FederationController::class, 'index']);
    Route::get('/federations/{public_id}', [FederationController::class, 'show']);

    Route::get('/fencers', [FencerController::class, 'index']);
    Route::get('/fencers/{public_id}', [FencerController::class, 'show']);

    Route::get('/standings', [StandingController::class, 'index']);
    Route::get('/standings/{public_id}', [StandingController::class, 'show']);

    Route::get('/seasons', [SeasonController::class, 'index']);
    Route::get('/seasons/{public_id}', [SeasonController::class, 'show']);

    Route::get('/seasons/{public_id}/standing', [SeasonController::class, 'standing']);

    Route::get('/tournaments/{public_id}', [TournamentController::class, 'show']);
});
