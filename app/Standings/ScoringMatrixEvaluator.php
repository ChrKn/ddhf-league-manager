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
        $applicable_rule = $this->ruleFor($participant_count);

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
     * Whether a tournament of this size counts at all.
     *
     * A field smaller than the smallest threshold in the matrix earns nobody anything: the
     * tournament is fenced and recorded, but not scored.
     */
    public function scores(int $participant_count): bool
    {
        return $this->ruleFor($participant_count) !== null;
    }

    /**
     * The matrix laid out for reading: one column per field size, one row per placement column.
     *
     * Every cell is looked up the way a result is scored - pointsForRank() at the first place of
     * the row - so the page shows what is computed, odd boundaries included (2019 has a band
     * that starts at 25 and a placement column that starts at 40). Rows a round can also land in
     * carry that round; pointsForRound() finds exactly that row for it.
     *
     * Bands at the bottom that award nothing at all are not columns: they are the threshold below
     * which a tournament earns nobody anything, and a column of zeros says that less clearly than
     * one sentence does.
     *
     * The pool exit is only a row where every band names it. A matrix that predates the column
     * scores a pool exit as the field size, which differs per tournament and is no single number.
     *
     * @return array{
     *     bands: list<array{min: int, max: ?int}>,
     *     rows: list<array{from: int, to: ?int, round: ?string, points: list<int>}>,
     *     pools: ?list<int>,
     *     threshold: ?int,
     *     max: int,
     * }
     */
    public function table(): array
    {
        $ascending = array_reverse($this->matrix);
        $dropped = false;

        // Leading bands that score nothing at all - points and pool exit - become the threshold.
        while ($ascending !== [] && self::awardsNothing($ascending[0])) {
            array_shift($ascending);
            $dropped = true;
        }

        $bands = [];

        foreach ($ascending as $index => $rule) {
            $next = $ascending[$index + 1]['participants']['min'] ?? null;
            $bands[] = [
                'min' => (int) ($rule['participants']['min'] ?? 0),
                'max' => $next === null ? null : (int) $next - 1,
            ];
        }

        $starts = [];
        $rounds = [];

        foreach ($ascending as $rule) {
            foreach ($rule['points'] ?? [] as $entry) {
                $from = (int) ($entry['place']['min'] ?? 0);
                $starts[$from] = true;

                if (isset($entry['round'])) {
                    $rounds[$from] ??= $entry['round'];
                }
            }
        }

        ksort($starts);
        $starts = array_keys($starts);

        $rows = [];
        $max = 0;

        foreach ($starts as $index => $from) {
            $points = array_map(fn (array $rule) => $this->pointsForRank($rule, $from), $ascending);
            $max = max($max, ...($points ?: [0]));

            $rows[] = [
                'from'   => $from,
                'to'     => isset($starts[$index + 1]) ? $starts[$index + 1] - 1 : null,
                'round'  => $rounds[$from] ?? null,
                'points' => $points,
            ];
        }

        $pools = null;

        if ($ascending !== [] && array_all($ascending, fn (array $rule) => isset($rule['pools']))) {
            $pools = array_map(fn (array $rule) => (int) $rule['pools'], $ascending);
            $max = max($max, ...$pools);
        }

        return [
            'bands'     => $bands,
            'rows'      => $rows,
            'pools'     => $pools,
            'threshold' => $dropped && $bands !== [] ? $bands[0]['min'] : null,
            'max'       => $max,
        ];
    }

    private static function awardsNothing(array $rule): bool
    {
        foreach ($rule['points'] ?? [] as $entry) {
            if (($entry['place']['points'] ?? 0) > 0) {
                return false;
            }
        }

        return ($rule['pools'] ?? 0) <= 0;
    }

    /**
     * The rules are sorted by descending participant threshold, so the first match is the most
     * specific one.
     */
    private function ruleFor(int $participant_count): ?array
    {
        foreach ($this->matrix as $rule) {
            if ($participant_count >= ($rule['participants']['min'] ?? 0)) {
                return $rule;
            }
        }

        return null;
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
