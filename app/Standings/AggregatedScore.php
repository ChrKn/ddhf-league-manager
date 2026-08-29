<?php

namespace App\Standings;

/**
 * The outcome of aggregating a fencer's scored results for one season.
 */
final class AggregatedScore
{
    /**
     * @param list<ScoredResult> $counted_results The results that actually contributed to the total.
     * @param string|null $qualifier The winning group in grouped modes, e.g. the zone or ruleset name.
     */
    public function __construct(
        public readonly int $points,
        public readonly array $counted_results,
        public readonly ?string $qualifier = null,
    ) {}

    public function counts(ScoredResult $result): bool
    {
        return in_array($result, $this->counted_results, true);
    }
}
