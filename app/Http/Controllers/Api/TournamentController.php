<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TournamentResource;
use App\Models\Tournament;

class TournamentController extends Controller
{
    public function show(string $public_id)
    {
        $tournament = Tournament::where('public_id', $public_id)
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
