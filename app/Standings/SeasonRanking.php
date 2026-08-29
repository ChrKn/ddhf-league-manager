<?php

namespace App\Standings;

use App\Models\ScoringMatrix;
use App\Models\Season;
use InvalidArgumentException;

/**
 * The table of one season, ranked and ready to be shown.
 *
 * Two pages show this now - the data browser and the public site - and the ranks are worked out
 * here rather than in either of them. Equal points share a rank and the ranks after them skip;
 * two views computing that separately would eventually disagree about who came fourth, and the
 * disagreement would look like a data problem rather than a code one.
 *
 * What an anonymised entry loses is decided here too, for the same reason: it keeps its rank and
 * its total, but not the club and not the list of tournaments the points came from.
 */
final class SeasonRanking
{
    /**
     * @return array{rows: list<array<string, mixed>>, mode: ScoringMode|null, error: string|null}
     */
    public static function for(Season $season): array
    {
        $evaluator = self::evaluatorFor($season->scoring_matrix);

        if ($evaluator === null) {
            return [
                'rows'  => [],
                'mode'  => null,
                'error' => 'Zu dieser Saison gibt es keinen brauchbaren Punkteschlüssel, '
                    . 'die Tabelle lässt sich nicht berechnen.',
            ];
        }

        $mode = $season->scoring_mode ?? ScoringMode::Standard;
        $standing = (new StandingCalculator($evaluator, $mode))->calculate($season);

        $rows = [];
        $rank = 0;
        $previous_points = null;

        foreach ($standing as $index => $entry) {
            // Equal points share a rank, and the ranks after them skip - the way a result list
            // reads.
            if ($entry['points'] !== $previous_points) {
                $rank = $index + 1;
                $previous_points = $entry['points'];
            }

            $anonymized = (bool) $entry['fencer']->isAnonymized();

            $rows[] = [
                'rank'   => $rank,
                'fencer' => $entry['fencer'],
                'name'   => $entry['fencer']->display_name,
                // Withheld for an anonymised entry: the club is the last trait that still points
                // at a person, and the list of tournaments is an itinerary.
                'group'      => $anonymized ? null : $entry['group'],
                'points'     => $entry['points'],
                'qualifier'  => $entry['qualifier'],
                'results'    => $anonymized ? [] : $entry['results'],
                'anonymized' => $anonymized,
            ];
        }

        return ['rows' => $rows, 'mode' => $mode, 'error' => null];
    }

    public static function evaluatorFor(?ScoringMatrix $matrix): ?ScoringMatrixEvaluator
    {
        if ($matrix === null) {
            return null;
        }

        try {
            return ScoringMatrixEvaluator::fromJson($matrix->matrix);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /** Everything a season needs loaded before its table can be built without N+1 queries. */
    public static function relations(): array
    {
        return [
            'standing.discipline',
            'standing.division',
            'scoring_matrix',
            'tournaments.results.fencer.group',
            'tournaments.ruleset',
        ];
    }
}
