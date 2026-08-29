<?php

namespace App\Standings\Aggregators;

use App\Standings\AggregatedScore;
use App\Standings\ScoredResult;

/**
 * Zone and ruleset system: the points are summed up per group, and only the best group counts
 * towards the standing - not the total across all groups.
 *
 * Both systems are the same algorithm over a different dimension, so the dimension is a
 * constructor parameter.
 *
 * Results that cannot be assigned to a group are dropped. Tournaments from before 2026 carry
 * neither a region nor a ruleset, so in these modes they do not score at all.
 */
final class BestGroupAggregator implements Aggregator
{
    public function __construct(private readonly string $dimension) {}

    public function aggregate(array $results): AggregatedScore
    {
        $groups = [];

        foreach ($results as $result) {
            $key = $result->groupKey($this->dimension);

            if ($key === null) {
                continue;
            }

            $groups[$key][] = $result;
        }

        if ($groups === []) {
            return new AggregatedScore(0, []);
        }

        // Sorting by key first makes the outcome deterministic when two groups tie on points.
        ksort($groups);

        $best_points = null;
        $best_group = [];

        foreach ($groups as $group) {
            $points = array_sum(array_map(fn(ScoredResult $result) => $result->points, $group));

            if ($best_points === null || $points > $best_points) {
                $best_points = $points;
                $best_group = $group;
            }
        }

        return new AggregatedScore(
            $best_points,
            $best_group,
            $best_group[0]->groupLabel($this->dimension),
        );
    }
}
