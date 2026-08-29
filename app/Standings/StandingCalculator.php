<?php

namespace App\Standings;

use App\Models\Federation;
use App\Models\Result;
use App\Models\Season;

/**
 * Builds the standing of a season: applies the scoring matrix to every result and then
 * aggregates the results per fencer according to the season's scoring mode.
 */
final class StandingCalculator
{
    public function __construct(
        private readonly ScoringMatrixEvaluator $evaluator,
        private readonly ScoringMode $mode,
    ) {}

    /**
     * The season is expected to come with tournaments.results.fencer and tournaments.ruleset
     * eager loaded.
     *
     * @return list<array{fencer: \App\Models\Fencer, group: string|null, points: int, qualifier: string|null, results: list<array<string, mixed>>}>
     */
    public function calculate(Season $season): array
    {
        $scored = $this->scoreResults($season);
        $aggregator = $this->mode->aggregator();
        $standing = [];

        foreach ($scored as $entry) {
            $score = $aggregator->aggregate($entry['results']);

            $standing[] = [
                'fencer'    => $entry['fencer'],
                'group'     => $entry['group'],
                'points'    => $score->points,
                'qualifier' => $score->qualifier,
                'results'   => $this->describe($entry['results'], $score),
            ];
        }

        usort($standing, function ($a, $b) {
            if ($a['points'] === $b['points']) {
                // By the shown name, not by the columns: an anonymised record has none, and
                // sorting by empty strings would gather those entries at the top of every tie.
                return strcmp($a['fencer']->display_name, $b['fencer']->display_name);
            }

            return $b['points'] <=> $a['points'];
        });

        return $standing;
    }

    /**
     * A fencer's results, in the order somebody reading them wants them.
     *
     * The ones the total is made of come first, best first, and the rest follow in the same order
     * behind them. In Best Three that is the whole point: three of five tournaments count and
     * nothing on the page said which three, because the list arrived in whatever order the
     * tournaments happened to be fenced in. It reads just as well in the grouped modes, where the
     * block on top is the zone or ruleset that won rather than the highest scores.
     *
     * Sorting here rather than in a view because three surfaces show this list - the public site,
     * the data browser and the API - and a reader comparing two of them should not have to work
     * out that they are looking at the same results in a different order.
     *
     * @param  list<ScoredResult>  $results
     * @return list<array<string, mixed>>
     */
    private function describe(array $results, AggregatedScore $score): array
    {
        // Ties are broken by the better placement and then by name, so that two results worth the
        // same points do not swap places between one page load and the next.
        usort($results, fn (ScoredResult $a, ScoredResult $b) => [
            !$score->counts($a), -$a->points, $a->placementSortKey(), $a->tournament_name,
        ] <=> [
            !$score->counts($b), -$b->points, $b->placementSortKey(), $b->tournament_name,
        ]);

        return array_map(fn (ScoredResult $result) => [
            'tournament_id' => $result->tournament_public_id,
            'tournament'    => $result->tournament_name,
            // The stored value and the same thing in German. A bracket result reads
            // "last-16" and has to arrive as "Achtelfinale" wherever a person sees it.
            'placement'      => $result->placement,
            'placement_name' => Placement::from($result->placement)->label(),
            'points'        => $result->points,
            'counts'        => $score->counts($result),
        ], $results);
    }

    /**
     * Group the raw results by fencer and attach the points earned for each of them.
     *
     * @return array<int, array{fencer: \App\Models\Fencer, group: string|null, results: list<ScoredResult>}>
     */
    private function scoreResults(Season $season): array
    {
        $scored = [];
        // Resolved once: the standing is a ranking of this federation's members, and every
        // result is measured against it. Null when the federation is not on record, in which
        // case nothing qualifies - a standing without its federation has no members to list.
        $own = Federation::own()?->id;

        // And once more for the category: who competed in the other division of this weapon and
        // year, and who declared which of the two they are ranked in. Both are empty unless the
        // season says a choice was required, so this costs nothing for the seasons that never
        // offered one.
        [$undecided, $chosen] = $this->divisionChoice($season);

        foreach ($season->tournaments as $tournament) {
            $participant_count = $tournament->participant_count ?? 0;

            foreach ($tournament->results as $result) {
                // Someone who asked to be removed stays in the standing, at the place they fenced
                // to, without a name. Leaving them out would move everybody behind them up a rank
                // and quietly rewrite a past season - and the rank of a person nobody can name is
                // not personal data. What identifies them was deleted from the record itself; see
                // AnonymizeFencerAction.

                // Only members are ranked, and membership is read off the result, not off the
                // person: the club someone fenced for back then is what counts, so a later club
                // change cannot add or remove a placement after the fact.
                //
                // Unless the federation granted an exception for this one result - the
                // Kulanzregelung, which lets the last tournament before a club joined count on
                // application. That is a decision somebody made and wrote down, so it is read here
                // rather than derived: no membership date can produce it. This standing is the own
                // federation's, so an exception granted here is one granted by it.
                if ($own === null || ($result->federation_id !== $own && !$result->countsOnRequest())) {
                    continue;
                }

                // A fencer who competed in both categories of this weapon belongs in one of them,
                // and only the declaration says which. Without one, neither: a name missing from
                // both lists is a question somebody will ask, a name in both is a wrong ranking
                // nobody notices.
                if (isset($undecided[$result->fencer_id]) && !isset($chosen[$result->fencer_id])) {
                    continue;
                }

                $fencer_id = $result->fencer_id;

                if (!isset($scored[$fencer_id])) {
                    $scored[$fencer_id] = [
                        'fencer'  => $result->fencer,
                        // The club as the database names it. results.fencer_group_name holds
                        // whatever spelling the source file used and stays out of the API.
                        //
                        // Null in two cases, and for two different reasons. Anonymising clears
                        // the club, and it is asked twice so that a record anonymised by hand
                        // with the club left behind still cannot name one. A club that asked not
                        // to be shown keeps every result pointing at it and every point it
                        // earned anybody - only its name goes.
                        'group'   => $result->fencer?->isAnonymized()
                            ? null
                            : $result->fencer?->group?->public_name,
                        'results' => [],
                    ];
                }

                $scored[$fencer_id]['results'][] = new ScoredResult(
                    fencer_id: $fencer_id,
                    tournament_public_id: $tournament->public_id,
                    tournament_name: $tournament->name ?: $tournament->display_name ?: '',
                    placement: (string) $result->placement,
                    points: $this->evaluator->pointsFor($participant_count, (string) $result->placement),
                    region: $tournament->region,
                    ruleset_id: $tournament->ruleset_id,
                    ruleset_name: $tournament->ruleset?->name,
                );
            }
        }

        return $scored;
    }

    /**
     * Who had a category to choose in this season, and who has chosen this one.
     *
     * A weapon can be fenced in two categories in the same year - *Damen+* and *offen* - and a
     * fencer is ranked in one of them. Only somebody who actually competed in both had anything to
     * choose; for everybody else the question never arises, which is why the first list is built
     * from results in the sibling seasons rather than from the field as a whole.
     *
     * Both lists stay empty unless the season asks for a choice. Until 2024 the federation ranked
     * the same person in both lists at once, so switching this on for those years would rewrite
     * standings that match the published ones.
     *
     * @return array{0: array<int, true>, 1: array<int, true>}
     */
    private function divisionChoice(Season $season): array
    {
        if (!$season->division_choice_required) {
            return [[], []];
        }

        $siblings = $season->siblings()->pluck('id');

        if ($siblings->isEmpty()) {
            return [[], []];
        }

        $undecided = Result::query()
            ->whereHas('tournament', fn ($query) => $query->whereIn('season_id', $siblings))
            ->whereNotNull('fencer_id')
            ->distinct()
            ->pluck('fencer_id')
            ->flip()
            ->map(fn () => true)
            ->all();

        $chosen = $season->fencers()->pluck('fencers.id')->flip()->map(fn () => true)->all();

        return [$undecided, $chosen];
    }
}
