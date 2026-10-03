<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TournamentResource;
use App\Models\Tournament;

class TournamentController extends Controller
{
    public function show(string $public_id)
    {
        // Not one of a season that is held back, which would hand out its results one by one.
        $tournament = Tournament::where('public_id', $public_id)
            ->public()
            ->with([
                'event',
                'ruleset',
                'season.standing.discipline',
                'season.standing.division',
                // fencer.group only covers the few results that predate fencer_group_name.
                'results.fencer.group',
            ])
            ->first();

        if (!$tournament) {
            return response()->json([
                'error' => 'Tournament not found.',
            ], 404);
        }

        return new TournamentResource($tournament);
    }
}
