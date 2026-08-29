<?php

namespace App\Standings\Aggregators;

use App\Standings\AggregatedScore;
use App\Standings\ScoredResult;

/**
 * Standard system: every tournament of the season counts, the points are simply added up.
 */
final class SumAggregator implements Aggregator
{
    public function aggregate(array $results): AggregatedScore
    {
        $points = array_sum(array_map(fn(ScoredResult $result) => $result->points, $results));

        return new AggregatedScore($points, array_values($results));
    }
}
