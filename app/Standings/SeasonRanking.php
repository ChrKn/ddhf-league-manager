<?php

namespace App\Standings;

use App\Models\ScoringMatrix;
use App\Models\Season;
use App\Models\Tournament;
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
     * @param  list<int>|null  $tournament_ids  The table as it stood after these tournaments; see
     *                                          steps(). Null for the table as it stands now.
     * @return array{rows: list<array<string, mixed>>, mode: ScoringMode|null, error: string|null}
     */
    public static function for(Season $season, ?array $tournament_ids = null): array
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
        $standing = (new StandingCalculator($evaluator, $mode))->calculate($season, $tournament_ids);

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

    /**
     * The points in the season at which the table can be looked at - one per event, like the
     * table after each matchday in a football league.
     *
     * By event rather than by import: an import is one event, but most seasons were entered long
     * before the importer existed, and the date that matters to a reader is the day it was fenced,
     * not the day somebody uploaded it.
     *
     * An event only becomes a step if at least one of its tournaments in this season was scored -
     * asked of SeasonScoring, like the table itself does, so the two cannot disagree. One whose
     * field was too small changed nobody's points, and a step that moves nothing would only make
     * the list longer. Its tournaments are still part of every step from then on - otherwise the
     * last step would not be the table as it stands now.
     *
     * Oldest first. Tournaments with no date at all go last, where they are in the current table
     * either way.
     *
     * @return list<array{key: string, label: string, tournament_ids: list<int>}>
     */
    public static function steps(Season $season): array
    {
        $scoring = SeasonScoring::of($season);

        if ($scoring === null) {
            return [];
        }

        $tournaments = $season->tournaments
            ->sortBy(fn (Tournament $tournament) => [
                $tournament->held_from === null ? 1 : 0,
                $tournament->held_from?->format('Y-m-d') ?? '',
                $tournament->event_id ?? PHP_INT_MAX,
                $tournament->id,
            ])
            ->values();

        // Grouped by event in the order the events first come up. A tournament without an event
        // is a step of its own.
        $groups = [];

        foreach ($tournaments as $tournament) {
            $key = $tournament->event?->public_id ?? $tournament->public_id;
            $groups[$key][] = $tournament;
        }

        $steps = [];
        $so_far = [];

        foreach ($groups as $key => $group) {
            foreach ($group as $tournament) {
                $so_far[] = $tournament->id;
            }

            $scored = array_filter(
                $group,
                fn (Tournament $tournament) => $scoring->scores($tournament)
            );

            if ($scored === []) {
                continue;
            }

            $first = $group[0];
            $date = $first->held_from?->format(__('site.date_format'));
            $name = trim((string) ($first->event?->name ?: ($first->name ?: $first->display_name)));
            $label = $date ? "{$date} · {$name}" : (string) $name;

            // A step that only exists because of a decision says so where it is chosen.
            if (array_filter($scored, fn (Tournament $tournament) => $scoring->scoresOnlyByDecision($tournament)) !== []) {
                $label .= ' · ' . __('site.standing.on_decision_short');
            }

            $steps[] = [
                'key'            => (string) $key,
                'label'          => $label,
                'tournament_ids' => $so_far,
            ];
        }

        // An unscored event after the last scored one still belongs in the table as it stands.
        if ($steps !== []) {
            $steps[array_key_last($steps)]['tournament_ids'] = $so_far;
        }

        return $steps;
    }

    /**
     * Attach to each row how many places it moved since an earlier table.
     *
     * `change` is the places gained - positive up, negative down, zero for none - and null for
     * somebody who was not in the earlier table at all. Matched by the fencer's record, so an
     * anonymised entry is followed just as well as a named one. Shared ranks are compared as they
     * stand: two fencers tied on fourth who were fifth and third have moved up one and down one.
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  list<array<string, mixed>>  $previous_rows
     * @return list<array<string, mixed>>
     */
    public static function withChanges(array $rows, array $previous_rows): array
    {
        $before = [];

        foreach ($previous_rows as $row) {
            $before[$row['fencer']->id] = $row['rank'];
        }

        return array_map(function (array $row) use ($before) {
            $previous = $before[$row['fencer']->id] ?? null;
            $row['change'] = $previous === null ? null : $previous - $row['rank'];

            return $row;
        }, $rows);
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
            'tournaments.event',
            'tournaments.results.fencer.group',
            'tournaments.ruleset',
        ];
    }
}
