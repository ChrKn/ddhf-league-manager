<?php

namespace App\Standings;

use InvalidArgumentException;

/**
 * Turns a tournament result into points, using the scoring matrix of a season.
 *
 * The matrix is the official points table, and that table is one grid with three headers stacked
 * on top of each other - "Platzierung / Runde / Finale" - plus a thirteenth column, "bzw. Pools".
 * So the same column answers three questions, and which one is being asked comes from the result
 * itself: a rank, a round, or a pool exit. See App\Standings\Placement and the README.
 */
final class ScoringMatrixEvaluator
{
    private readonly array $matrix;

    public function __construct(array $matrix)
    {
        $this->matrix = self::normalize($matrix);
    }

    /**
     * @throws InvalidArgumentException When the stored JSON does not decode to a matrix.
     */
    public static function fromJson(?string $json): self
    {
        $matrix = json_decode((string) $json, true);

        if (!is_array($matrix)) {
            throw new InvalidArgumentException('Scoring matrix is not valid JSON.');
        }

        return new self($matrix);
    }

    /**
     * @param  string  $placement  A rank ("17"), a round ("last-16") or "pools".
     *
     * @throws InvalidArgumentException When the placement is none of those.
     */
    public function pointsFor(int $participant_count, string $placement): int
    {
        // The rules are sorted by descending participant threshold, so the first match is the
        // most specific one.
        $applicable_rule = null;

        foreach ($this->matrix as $rule) {
            if ($participant_count >= ($rule['participants']['min'] ?? 0)) {
                $applicable_rule = $rule;
                break;
            }
        }

        if ($applicable_rule === null) {
            return 0;
        }

        $placement = Placement::from($placement);

        if ($placement->isRank()) {
            return $this->pointsForRank($applicable_rule, $placement->rank);
        }

        if ($placement->isPools()) {
            // The thirteenth column, one point at every field size. A matrix that predates it is
            // read the way the older imports wrote a pool exit: parked on the field size, which
            // lands in the last column and so on the same one point.
            return isset($applicable_rule['pools'])
                ? (int) $applicable_rule['pools']
                : $this->pointsForRank($applicable_rule, $participant_count);
        }

        return $this->pointsForRound($applicable_rule, $placement->round_size);
    }

    /**
     * The round someone went out in, mapped onto the column that names it.
     *
     * Rounded up, because not every bracket is a power of two: the Symphony of Steel 2025 had a
     * round of eighteen, and whoever went out in it had not reached the round of sixteen. So they
     * belong in the column below it, which is what the older imports arrived at as well.
     */
    private function pointsForRound(array $rule, int $size): int
    {
        $column = null;

        foreach ($rule['points'] ?? [] as $entry) {
            if (!isset($entry['round'])) {
                continue;
            }

            $named = Placement::from($entry['round'])->round_size;

            if ($named !== null && $named >= $size && ($column === null || $named < $column['size'])) {
                $column = ['size' => $named, 'points' => $entry['place']['points'] ?? 0];
            }
        }

        if ($column !== null) {
            return (int) $column['points'];
        }

        // Either this matrix describes no rounds at all - the ones from 2019 to 2021 do not - or
        // the round is bigger than any column it has. Reading the size as a placement is what the
        // imports did before rounds were recorded, and it finds the same column.
        return $this->pointsForRank($rule, $size);
    }

    private function pointsForRank(array $rule, int $rank): int
    {
        // The placements are sorted ascending, so the last match is the applicable bracket.
        $points = 0;

        foreach ($rule['points'] ?? [] as $point_rule) {
            if ($rank >= ($point_rule['place']['min'] ?? 0)) {
                $points = $point_rule['place']['points'] ?? 0;
            }
        }

        return (int) $points;
    }

    /**
     * The lookups above depend on a defined order. The stored JSON carries whatever order it was
     * imported with, so sort it once here instead of relying on it.
     */
    private static function normalize(array $matrix): array
    {
        usort($matrix, function ($a, $b) {
            return ($b['participants']['min'] ?? 0) <=> ($a['participants']['min'] ?? 0);
        });

        foreach ($matrix as &$rule) {
            if (!isset($rule['points']) || !is_array($rule['points'])) {
                continue;
            }

            usort($rule['points'], function ($a, $b) {
                return ($a['place']['min'] ?? 0) <=> ($b['place']['min'] ?? 0);
            });
        }
        unset($rule);

        return $matrix;
    }
}
