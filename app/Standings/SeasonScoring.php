<?php

namespace App\Standings;

use App\Models\Season;
use App\Models\Tournament;

/**
 * Whether a tournament of a season is scored, and what its placements are worth.
 *
 * The one place that answers the first question. The standing, the steps through it, the
 * tournament page, its JSON, the data browser, the API and the exports all ask it here, because
 * each of them asking on its own is how a tournament ends up counted in the table and shown as
 * unscored on its own page.
 *
 * Two things decide it, and they are kept apart on purpose:
 *
 * - The season's threshold, `seasons.min_participants`: the smallest field the season scores. It is
 *   a rule of the season, not of the table - the official table is the same for every standing,
 *   and only open longsword leaves out its three smallest columns. Null means the season has no
 *   rule of its own and the matrix alone decides, which is how every season stood before.
 *
 * - The matrix: there has to be a column for the field that awards something. This half is never
 *   lifted. A field below the table's first real column earns nobody anything, because there is
 *   nothing to read the points from.
 *
 * The points always come from the season's matrix, at the field size that was actually fenced.
 * Nothing here can move a tournament into a bigger column than its field.
 */
final class SeasonScoring
{
    public function __construct(
        public readonly ScoringMatrixEvaluator $matrix,
        public readonly ?int $min_participants = null,
    ) {}

    public static function for(Season $season, ScoringMatrixEvaluator $matrix): self
    {
        return new self($matrix, $season->min_participants === null ? null : (int) $season->min_participants);
    }

    /**
     * For a season on its own. Null where its matrix is missing or does not decode - the same
     * answer SeasonRanking::evaluatorFor() gives, which is what the pages already handle.
     */
    public static function of(?Season $season): ?self
    {
        $matrix = SeasonRanking::evaluatorFor($season?->scoring_matrix);

        return $season === null || $matrix === null ? null : self::for($season, $matrix);
    }

    /** Whether this tournament counts towards the standing at all. */
    public function scores(Tournament $tournament): bool
    {
        $participants = (int) $tournament->participant_count;

        return $this->clearsThreshold($tournament) && $this->matrix->hasBandFor($participants);
    }

    /**
     * Whether the season's own threshold lets this tournament through. True where the season has
     * none: the matrix then decides alone.
     */
    public function clearsThreshold(Tournament $tournament): bool
    {
        return $this->min_participants === null
            || (int) $tournament->participant_count >= $this->min_participants;
    }

    /**
     * What one placement in this tournament is worth - nothing at all where it is not scored.
     *
     * By participant_count only, which is the field that fenced. How many had registered is on the
     * tournament as well and deliberately read nowhere on the way to a point: a field size that a
     * longer registration list could raise would be one worth raising.
     */
    public function pointsFor(Tournament $tournament, string $placement): int
    {
        if (!$this->scores($tournament)) {
            return 0;
        }

        return $this->matrix->pointsFor((int) $tournament->participant_count, $placement);
    }
}
