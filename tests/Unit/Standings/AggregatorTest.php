<?php

namespace Tests\Unit\Standings;

use App\Standings\Aggregators\BestGroupAggregator;
use App\Standings\Aggregators\BestNAggregator;
use App\Standings\Aggregators\SumAggregator;
use App\Standings\ScoredResult;
use App\Standings\ScoringMode;
use PHPUnit\Framework\TestCase;

class AggregatorTest extends TestCase
{
    private function scored(
        int $points,
        ?string $region = null,
        ?int $ruleset_id = null,
        int $placement = 1,
        ?string $ruleset_name = null
    ): ScoredResult {
        return new ScoredResult(
            fencer_id: 1,
            tournament_public_id: 'T' . $points . ($region ?? '') . ($ruleset_id ?? ''),
            tournament_name: 'Turnier',
            placement: $placement,
            points: $points,
            region: $region,
            ruleset_id: $ruleset_id,
            ruleset_name: $ruleset_name,
        );
    }

    // Standard

    public function test_standard_adds_up_every_result(): void
    {
        $score = (new SumAggregator())->aggregate([
            $this->scored(10),
            $this->scored(5),
            $this->scored(3),
        ]);

        $this->assertSame(18, $score->points);
        $this->assertCount(3, $score->counted_results);
        $this->assertNull($score->qualifier);
    }

    public function test_standard_handles_a_fencer_without_results(): void
    {
        $score = (new SumAggregator())->aggregate([]);

        $this->assertSame(0, $score->points);
        $this->assertSame([], $score->counted_results);
    }

    // Best Three

    public function test_best_three_adds_up_only_the_three_highest_results(): void
    {
        $score = (new BestNAggregator(3))->aggregate([
            $this->scored(4),
            $this->scored(12),
            $this->scored(1),
            $this->scored(9),
            $this->scored(6),
        ]);

        $this->assertSame(27, $score->points);
        $this->assertCount(3, $score->counted_results);
    }

    public function test_best_three_marks_the_right_results_as_counting(): void
    {
        $best = $this->scored(12);
        $second = $this->scored(9);
        $third = $this->scored(6);
        $ignored = $this->scored(1);

        $score = (new BestNAggregator(3))->aggregate([$ignored, $best, $third, $second]);

        $this->assertTrue($score->counts($best));
        $this->assertTrue($score->counts($second));
        $this->assertTrue($score->counts($third));
        $this->assertFalse($score->counts($ignored));
    }

    public function test_best_three_falls_back_to_all_results_when_there_are_fewer_than_three(): void
    {
        $score = (new BestNAggregator(3))->aggregate([
            $this->scored(10),
            $this->scored(5),
        ]);

        $this->assertSame(15, $score->points);
        $this->assertCount(2, $score->counted_results);
    }

    public function test_best_three_breaks_ties_by_the_better_placement(): void
    {
        $better_placement = $this->scored(5, placement: 2);
        $worse_placement = $this->scored(5, placement: 8);

        $score = (new BestNAggregator(1))->aggregate([$worse_placement, $better_placement]);

        $this->assertTrue($score->counts($better_placement));
        $this->assertFalse($score->counts($worse_placement));
    }

    // Zone

    public function test_zone_counts_only_the_strongest_zone(): void
    {
        $score = (new BestGroupAggregator(ScoredResult::DIMENSION_REGION))->aggregate([
            $this->scored(5, region: 'north'),
            $this->scored(4, region: 'north'),
            $this->scored(12, region: 'south'),
        ]);

        // 12 from the south beats 9 from the north; the sum across zones (21) is never used.
        $this->assertSame(12, $score->points);
        $this->assertCount(1, $score->counted_results);
        $this->assertSame('Süden', $score->qualifier);
    }

    public function test_zone_sums_up_within_a_zone(): void
    {
        $score = (new BestGroupAggregator(ScoredResult::DIMENSION_REGION))->aggregate([
            $this->scored(5, region: 'north'),
            $this->scored(6, region: 'north'),
            $this->scored(10, region: 'south'),
        ]);

        $this->assertSame(11, $score->points);
        $this->assertSame('Norden', $score->qualifier);
    }

    public function test_zone_discards_results_without_a_region(): void
    {
        $without_region = $this->scored(20);
        $with_region = $this->scored(3, region: 'east');

        $score = (new BestGroupAggregator(ScoredResult::DIMENSION_REGION))->aggregate([
            $without_region,
            $with_region,
        ]);

        $this->assertSame(3, $score->points);
        $this->assertFalse($score->counts($without_region));
        $this->assertTrue($score->counts($with_region));
    }

    public function test_zone_yields_no_points_when_no_result_has_a_region(): void
    {
        // The situation for every season before 2026, where the region was never recorded.
        $score = (new BestGroupAggregator(ScoredResult::DIMENSION_REGION))->aggregate([
            $this->scored(20),
            $this->scored(15),
        ]);

        $this->assertSame(0, $score->points);
        $this->assertSame([], $score->counted_results);
        $this->assertNull($score->qualifier);
    }

    // Ruleset

    public function test_ruleset_counts_only_the_strongest_ruleset(): void
    {
        $score = (new BestGroupAggregator(ScoredResult::DIMENSION_RULESET))->aggregate([
            $this->scored(5, ruleset_id: 1, ruleset_name: 'Regelwerk A'),
            $this->scored(4, ruleset_id: 1, ruleset_name: 'Regelwerk A'),
            $this->scored(12, ruleset_id: 2, ruleset_name: 'Regelwerk B'),
        ]);

        $this->assertSame(12, $score->points);
        $this->assertSame('Regelwerk B', $score->qualifier);
    }

    public function test_ruleset_discards_results_without_a_ruleset(): void
    {
        $score = (new BestGroupAggregator(ScoredResult::DIMENSION_RULESET))->aggregate([
            $this->scored(20),
            $this->scored(3, ruleset_id: 7, ruleset_name: 'Regelwerk A'),
        ]);

        $this->assertSame(3, $score->points);
    }

    // Mode wiring

    public function test_every_mode_resolves_to_an_aggregator(): void
    {
        foreach (ScoringMode::cases() as $mode) {
            $this->assertNotNull($mode->aggregator());
            $this->assertNotSame('', $mode->label());
        }
    }

    public function test_zone_and_ruleset_modes_use_different_dimensions(): void
    {
        $results = [
            $this->scored(10, region: 'north'),
            $this->scored(4, ruleset_id: 1, ruleset_name: 'Regelwerk A'),
        ];

        $this->assertSame(10, ScoringMode::Zone->aggregator()->aggregate($results)->points);
        $this->assertSame(4, ScoringMode::Ruleset->aggregator()->aggregate($results)->points);
        $this->assertSame(14, ScoringMode::Standard->aggregator()->aggregate($results)->points);
    }
}
