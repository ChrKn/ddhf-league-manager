<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SeasonResource;
use App\Models\ScoringMatrix;
use App\Models\Season;
use App\Standings\ScoringMatrixEvaluator;
use App\Standings\ScoringMode;
use App\Standings\StandingCalculator;
use InvalidArgumentException;

class SeasonController extends Controller
{
    public function index()
    {
        return SeasonResource::collection(
            Season::query()
                ->with(['standing.discipline', 'standing.division'])
                ->get()
        );
    }

    public function show(string $public_id)
    {
        $season = Season::where('public_id', $public_id)
            ->with(['standing.discipline', 'standing.division'])
            ->first();

        if (!$season) {
            return response()->json([
                'error' => 'Season not found.',
            ], 404);
        }

        return new SeasonResource($season);
    }

    public function standing(string $public_id)
    {
        $season = Season::where('public_id', $public_id)
            ->with(['tournaments.results.fencer.group', 'tournaments.ruleset'])
            ->first();

        if (!$season) {
            return response()->json([
                'error' => 'Season not found.',
            ], 404);
        }

        $scoring_matrix = ScoringMatrix::find($season->scoring_matrix_id);

        if (!$scoring_matrix) {
            return response()->json([
                'error' => 'Scoring matrix for season not found.',
            ], 404);
        }

        try {
            $evaluator = ScoringMatrixEvaluator::fromJson($scoring_matrix->matrix);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'error' => 'Scoring matrix for season is not valid JSON.',
            ], 500);
        }

        $mode = $season->scoring_mode ?? ScoringMode::Standard;
        $standing = (new StandingCalculator($evaluator, $mode))->calculate($season);

        return response()->json([
            'season_id'    => $season->public_id,
            'season_name'  => $season->display_name,
            'scoring_mode' => $mode->value,
            'standing'     => array_map(function ($entry) {
                // An anonymised fencer keeps their rank but hands out no id: /api/fencers answers
                // 404 for them, so an id here would be a link to nowhere - and following it is
                // exactly how a consumer would try to put the name back.
                $anonymized = (bool) $entry['fencer']->isAnonymized();

                return [
                    'fencer'    => [
                        'id'    => $anonymized ? null : $entry['fencer']->public_id,
                        'name'  => $entry['fencer']->display_name,
                        'group' => $anonymized ? null : $entry['group'],
                    ],
                    'points'    => $entry['points'],
                    'qualifier' => $entry['qualifier'],
                    // The single results go too. Which tournaments somebody attended, on which
                    // dates, with which placements is an itinerary, and against a start list or a
                    // photo of a prize giving it identifies a person as surely as a name would.
                    // The total stays, because that is what the rank is made of and it says
                    // nothing about where the points were earned.
                    'results'   => $anonymized ? [] : $entry['results'],
                ];
            }, $standing),
        ]);
    }
}
