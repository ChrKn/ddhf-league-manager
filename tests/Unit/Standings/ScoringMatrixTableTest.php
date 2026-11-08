<?php

namespace Tests\Unit\Standings;

use App\Standings\ScoringMatrixEvaluator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The matrix as the scoring page lays it out. What matters most is that it shows what is computed,
 * so every cell is checked against pointsFor() rather than against numbers copied from the file.
 */
class ScoringMatrixTableTest extends TestCase
{
    private static function fromFile(string $name): ScoringMatrixEvaluator
    {
        return ScoringMatrixEvaluator::fromJson(
            file_get_contents(__DIR__ . "/../../../database/scoring-matrices/{$name}.json")
        );
    }

    public static function matrices(): array
    {
        return array_map(
            fn (string $path) => [basename($path, '.json')],
            glob(__DIR__ . '/../../../database/scoring-matrices/*.json')
        );
    }

    #[DataProvider('matrices')]
    public function test_every_cell_is_what_a_result_there_would_score(string $name): void
    {
        $evaluator = self::fromFile($name);
        $table = $evaluator->table();

        $this->assertNotEmpty($table['rows']);

        foreach ($table['rows'] as $row) {
            foreach ($table['bands'] as $index => $band) {
                // Both ends of the band and both ends of the row, where the boundaries could slip.
                foreach (array_filter([$band['min'], $band['max']]) as $participants) {
                    foreach (array_filter([$row['from'], $row['to']]) as $place) {
                        $this->assertSame(
                            $evaluator->pointsFor($participants, (string) $place),
                            $row['points'][$index],
                            "{$name}: {$participants} entrants, place {$place}"
                        );
                    }
                }

                if ($row['round'] !== null) {
                    $this->assertSame($evaluator->pointsFor(max(1, $band['min']), $row['round']), $row['points'][$index]);
                }
            }
        }

        if ($table['pools'] !== null) {
            foreach ($table['bands'] as $index => $band) {
                $this->assertSame($evaluator->pointsFor($band['min'], 'pools'), $table['pools'][$index]);
            }
        }
    }

    public function test_the_2026_matrix_drops_the_empty_band_and_names_the_threshold(): void
    {
        $table = self::fromFile('2026-standard')->table();

        $this->assertSame(5, $table['threshold']);
        $this->assertSame(['min' => 5, 'max' => 8], $table['bands'][0]);
        $this->assertSame(['min' => 129, 'max' => null], $table['bands'][array_key_last($table['bands'])]);

        $this->assertSame(['from' => 1, 'to' => 1], array_intersect_key($table['rows'][0], ['from' => 0, 'to' => 0]));
        $this->assertSame(46, $table['rows'][0]['points'][array_key_last($table['bands'])]);
        $this->assertSame(46, $table['max']);

        $quarter = array_values(array_filter($table['rows'], fn ($row) => $row['from'] === 7))[0];
        $this->assertSame(8, $quarter['to']);
        $this->assertSame('last-8', $quarter['round']);

        $this->assertSame(array_fill(0, count($table['bands']), 1), $table['pools']);
    }

    public function test_the_2019_matrix_keeps_its_lowest_band_and_has_no_pool_row(): void
    {
        $table = self::fromFile('2019-langschwert')->table();

        $this->assertNull($table['threshold']);
        $this->assertSame(['min' => 0, 'max' => 24], $table['bands'][0]);
        $this->assertNull($table['pools']);
        $this->assertSame([null], array_values(array_unique(array_column($table['rows'], 'round'))));
    }

    public function test_an_empty_matrix_is_an_empty_table(): void
    {
        $table = (new ScoringMatrixEvaluator([]))->table();

        $this->assertSame([], $table['bands']);
        $this->assertSame([], $table['rows']);
        $this->assertNull($table['pools']);
        $this->assertNull($table['threshold']);
    }
}
