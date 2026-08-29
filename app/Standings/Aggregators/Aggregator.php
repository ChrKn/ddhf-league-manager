<?php

namespace App\Standings\Aggregators;

use App\Standings\AggregatedScore;
use App\Standings\ScoredResult;

interface Aggregator
{
    /**
     * Turn all scored results of a single fencer into that fencer's season total.
     *
     * @param list<ScoredResult> $results
     */
    public function aggregate(array $results): AggregatedScore;
}
