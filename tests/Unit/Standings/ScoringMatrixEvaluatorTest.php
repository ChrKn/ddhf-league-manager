<?php

namespace Tests\Unit\Standings;

use App\Standings\ScoringMatrixEvaluator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ScoringMatrixEvaluatorTest extends TestCase
{
    /**
     * The 2019 longsword matrix from the README, with the rules and placements deliberately
     * shuffled: the evaluator must not depend on the order it was imported in.
     */
    private function shuffledMatrix(): array
    {
        return [
            ['participants' => ['min' => 0], 'points' => [
                ['place' => ['min' => 4, 'points' => 2]],
                ['place' => ['min' => 1, 'points' => 9]],
                ['place' => ['min' => 9, 'points' => 1]],
                ['place' => ['min' => 3, 'points' => 3]],
                ['place' => ['min' => 2, 'points' => 6]],
            ]],
            ['participants' => ['min' => 40], 'points' => [
                ['place' => ['min' => 26, 'points' => 2]],
                ['place' => ['min' => 9, 'points' => 4]],
                ['place' => ['min' => 1, 'points' => 20]],
                ['place' => ['min' => 41, 'points' => 1]],
                ['place' => ['min' => 4, 'points' => 6]],
                ['place' => ['min' => 2, 'points' => 15]],
                ['place' => ['min' => 3, 'points' => 10]],
            ]],
            ['participants' => ['min' => 25], 'points' => [
                ['place' => ['min' => 9, 'points' => 2]],
                ['place' => ['min' => 26, 'points' => 1]],
                ['place' => ['min' => 1, 'points' => 12]],
                ['place' => ['min' => 3, 'points' => 4]],
                ['place' => ['min' => 4, 'points' => 3]],
                ['place' => ['min' => 2, 'points' => 8]],
            ]],
        ];
    }

    public static function placementProvider(): array
    {
        return [
            'large, winner'      => [50, 1, 20],
            'large, second'      => [50, 2, 15],
            'large, third'       => [50, 3, 10],
            'large, bracket 4'   => [50, 5, 6],
            'large, bracket 9'   => [50, 10, 4],
            'large, bracket 26'  => [50, 30, 2],
            'large, bracket 41'  => [50, 45, 1],
            'medium, winner'     => [30, 1, 12],
            'medium, third'      => [30, 3, 4],
            'medium, bracket 9'  => [30, 12, 2],
            'medium, bracket 26' => [30, 30, 1],
            'small, winner'      => [10, 1, 9],
            'small, second'      => [10, 2, 6],
            'small, bracket 4'   => [10, 4, 2],
            'small, bracket 9'   => [10, 15, 1],
            'boundary at 40'     => [40, 1, 20],
            'boundary at 25'     => [25, 1, 12],
            'boundary below 25'  => [24, 1, 9],
        ];
    }

    #[DataProvider('placementProvider')]
    public function test_it_awards_points_regardless_of_matrix_order(
        int $participant_count,
        int $placement,
        int $expected
    ): void {
        $evaluator = new ScoringMatrixEvaluator($this->shuffledMatrix());

        $this->assertSame($expected, $evaluator->pointsFor($participant_count, $placement));
    }

    public function test_it_awards_no_points_when_no_rule_applies(): void
    {
        $evaluator = new ScoringMatrixEvaluator([
            ['participants' => ['min' => 100], 'points' => [['place' => ['min' => 1, 'points' => 5]]]],
        ]);

        $this->assertSame(0, $evaluator->pointsFor(10, 1));
    }

    public function test_it_awards_no_points_for_a_placement_below_every_bracket(): void
    {
        $evaluator = new ScoringMatrixEvaluator([
            ['participants' => ['min' => 0], 'points' => [['place' => ['min' => 5, 'points' => 5]]]],
        ]);

        $this->assertSame(0, $evaluator->pointsFor(10, 3));
    }

    public function test_it_rejects_invalid_json(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ScoringMatrixEvaluator::fromJson('not json at all');
    }

    public function test_it_reads_a_matrix_from_json(): void
    {
        $evaluator = ScoringMatrixEvaluator::fromJson(
            '[{"participants":{"min":0},"points":[{"place":{"min":1,"points":7}}]}]'
        );

        $this->assertSame(7, $evaluator->pointsFor(10, 1));
    }

    /**
     * The 33-48 band of Punkteschlüssel 2022+, with all three readings of its columns - the row
     * the official graphic prints as 22, 18, 15, 12, 10, 8, 6, 4, 3, 2, 1, -, and 1 for pools.
     */
    private function tableWithRounds(): ScoringMatrixEvaluator
    {
        return new ScoringMatrixEvaluator([
            ['participants' => ['min' => 33], 'pools' => 1, 'points' => [
                ['place' => ['min' => 1,  'points' => 22]],
                ['place' => ['min' => 2,  'points' => 18]],
                ['place' => ['min' => 3,  'points' => 15]],
                ['place' => ['min' => 4,  'points' => 12]],
                ['place' => ['min' => 5,  'points' => 10], 'round' => 'last-6'],
                ['place' => ['min' => 7,  'points' => 8],  'round' => 'last-8'],
                ['place' => ['min' => 9,  'points' => 6],  'round' => 'last-12'],
                ['place' => ['min' => 13, 'points' => 4],  'round' => 'last-16'],
                ['place' => ['min' => 17, 'points' => 3],  'round' => 'last-24'],
                ['place' => ['min' => 25, 'points' => 2],  'round' => 'last-32'],
                ['place' => ['min' => 33, 'points' => 1],  'round' => 'last-48'],
                ['place' => ['min' => 49, 'points' => 0],  'round' => 'last-64'],
            ]],
            ['participants' => ['min' => 0], 'pools' => 0, 'points' => [
                ['place' => ['min' => 1, 'points' => 0]],
            ]],
        ]);
    }

    public static function readingProvider(): array
    {
        return [
            // The first four columns have no round name and never needed one.
            'winner'                => ['1', 22],
            'lost the final'        => ['2', 18],
            'lost the third place'  => ['4', 12],
            // The round reading. Each of these is the column the placement range shares.
            'quarter final'         => ['last-8', 8],
            'eighth final'          => ['last-16', 4],
            'sixteenth final'       => ['last-32', 2],
            'round of six'          => ['last-6', 10],
            'round of twelve'       => ['last-12', 6],
            // The placement reading of the same columns, which has to agree.
            'placed seventh'        => ['7', 8],
            'placed sixteenth'      => ['16', 4],
            // The thirteenth column, whatever the field size.
            'knocked out in pools'  => ['pools', 1],
        ];
    }

    #[DataProvider('readingProvider')]
    public function test_it_reads_all_three_headers_of_the_same_columns(string $placement, int $expected): void
    {
        $this->assertSame($expected, $this->tableWithRounds()->pointsFor(42, $placement));
    }

    public function test_a_round_the_table_has_no_column_for_is_rounded_up(): void
    {
        // The Symphony of Steel 2025 fenced a round of eighteen. Whoever went out in it had not
        // reached the round of sixteen, so they score the column below - and that is the value the
        // published list gives them.
        $this->assertSame(3, $this->tableWithRounds()->pointsFor(42, 'last-18'));

        // Not the round of sixteen, which would be a point more.
        $this->assertNotSame(4, $this->tableWithRounds()->pointsFor(42, 'last-18'));
    }

    public function test_the_pool_exit_of_a_large_field_is_still_one_point(): void
    {
        // The whole reason for the thirteenth column. Read as a placement, someone knocked out in
        // the pools of a 42 fencer field would sit on rank 17 to 42 and collect two or three.
        $evaluator = $this->tableWithRounds();

        $this->assertSame(3, $evaluator->pointsFor(42, '17'));
        $this->assertSame(2, $evaluator->pointsFor(42, '28'));
        $this->assertSame(1, $evaluator->pointsFor(42, 'pools'));
    }

    public function test_a_band_that_scores_nothing_scores_nothing_for_pools(): void
    {
        $this->assertSame(0, $this->tableWithRounds()->pointsFor(4, 'pools'));
    }

    public function test_a_matrix_without_rounds_reads_the_key_as_the_number_behind_it(): void
    {
        // The 2019 to 2021 matrices have a different set of columns and no source describing
        // their rounds. A round key must not silently zero somebody there - it is read the way
        // the imports of those years wrote it, as the worst placement of the round.
        $evaluator = new ScoringMatrixEvaluator($this->shuffledMatrix());

        $this->assertSame($evaluator->pointsFor(50, '30'), $evaluator->pointsFor(50, 'last-30'));
        // A pool exit was parked on the field size back then, which is where it still lands.
        $this->assertSame($evaluator->pointsFor(50, '50'), $evaluator->pointsFor(50, 'pools'));
    }

    public function test_it_rejects_a_placement_it_cannot_read(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->tableWithRounds()->pointsFor(42, 'Halbfinale');
    }
}
