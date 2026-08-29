<?php

namespace App\Standings;

use App\Data\Regions;

/**
 * A single tournament result of a fencer after the scoring matrix has been applied to it.
 *
 * Carries the dimensions the aggregators group by, so that the aggregation step does not
 * have to touch the database again.
 */
final class ScoredResult
{
    public const DIMENSION_REGION = 'region';

    public const DIMENSION_RULESET = 'ruleset';

    public function __construct(
        public readonly int $fencer_id,
        public readonly ?string $tournament_public_id,
        public readonly string $tournament_name,
        /** A rank ("17"), a round ("last-16") or "pools" - see App\Standings\Placement. */
        public readonly string $placement,
        public readonly int $points,
        public readonly ?string $region = null,
        public readonly ?int $ruleset_id = null,
        public readonly ?string $ruleset_name = null,
    ) {}

    /** Orders results the way the points table reads, left to right. */
    public function placementSortKey(): int
    {
        return Placement::from($this->placement)->sortKey();
    }

    /**
     * The value a grouping aggregator buckets this result by. A null value means the result
     * cannot be assigned to a group and is dropped from that evaluation.
     */
    public function groupKey(string $dimension): ?string
    {
        return match ($dimension) {
            self::DIMENSION_REGION  => $this->region,
            self::DIMENSION_RULESET => $this->ruleset_id !== null ? (string) $this->ruleset_id : null,
            default                 => null,
        };
    }

    /**
     * The human-readable name of the group, used to tell API consumers which zone or ruleset won.
     */
    public function groupLabel(string $dimension): ?string
    {
        return match ($dimension) {
            self::DIMENSION_REGION  => Regions::label($this->region),
            self::DIMENSION_RULESET => $this->ruleset_name,
            default                 => null,
        };
    }
}
