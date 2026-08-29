<?php

namespace App\Standings\Aggregators;

use App\Standings\AggregatedScore;
use App\Standings\ScoredResult;

/**
 * Best Three system: only the n highest scoring tournaments of a fencer are added up.
 *
 * The count is a constructor parameter because the federation may well settle on a different
 * number than three in a future season.
 */
final class BestNAggregator implements Aggregator
{
    public function __construct(private readonly int $count) {}

    public function aggregate(array $results): AggregatedScore
    {
        $sorted = array_values($results);

        // Highest points first. Ties are broken by the better placement so that the selection is
        // deterministic and the fencer keeps the result they actually fenced better. Compared
        // through the sort key, because a placement may read "last-16" rather than a number.
        usort($sorted, function (ScoredResult $a, ScoredResult $b) {
            return [$b->points, $a->placementSortKey()] <=> [$a->points, $b->placementSortKey()];
        });

        $counted = array_slice($sorted, 0, $this->count);
        $points = array_sum(array_map(fn(ScoredResult $result) => $result->points, $counted));

        return new AggregatedScore($points, $counted);
    }
}
