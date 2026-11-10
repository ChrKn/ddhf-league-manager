<?php

namespace App\Standings;

use App\Models\Tournament;

/**
 * The field of one tournament, ordered and scored the way the public site shows it.
 *
 * Two things read this - the tournament page and its JSON - and they must not disagree about who
 * stands where or what a club is called, which is why it is worked out here rather than in either.
 * Like SeasonRanking for a standing, it is also where an anonymised entry loses its club.
 *
 * The placement is handed out as it is stored. Each reader turns it into words in its own language.
 */
final class TournamentResults
{
    /** What has to be loaded on the tournament for this to run without N+1 queries. */
    public const RELATIONS = ['season.scoring_matrix', 'results.fencer', 'results.group'];

    /**
     * @return list<array{placement: Placement, fencer: string|null, anonymized: bool, group: string|null, points: int|null}>
     */
    public static function for(Tournament $tournament): array
    {
        // Through SeasonScoring, which the standing asks as well: a tournament below the season's
        // threshold shows nobody points here that the table does not give them.
        $scoring = SeasonScoring::of($tournament->season);

        // Ordered the way a result list reads: the ranks, then the rounds from the latest to the
        // earliest, then whoever never left the pools. Through Placement, because sorted as text
        // "last-16" would come before "last-8".
        return $tournament->results
            ->sortBy(fn ($result) => [
                Placement::from((string) $result->placement)->sortKey(),
                $result->fencer?->display_name ?? '',
            ])
            ->map(function ($result) use ($scoring, $tournament) {
                $anonymized = (bool) $result->fencer?->isAnonymized();

                return [
                    'placement'  => Placement::from((string) $result->placement),
                    'fencer'     => $result->fencer?->display_name,
                    'anonymized' => $anonymized,
                    // The club as the database names it, and nothing at all for an anonymised
                    // entry or a club that asked not to be shown. The raw spelling from the
                    // source file stays out of this site either way.
                    'group'      => $anonymized ? null : $result->group?->public_name,
                    'points'     => $scoring?->pointsFor($tournament, (string) $result->placement),
                ];
            })
            ->values()
            ->all();
    }
}
