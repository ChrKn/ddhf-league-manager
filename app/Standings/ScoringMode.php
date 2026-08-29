<?php

namespace App\Standings;

use App\Standings\Aggregators\Aggregator;
use App\Standings\Aggregators\BestGroupAggregator;
use App\Standings\Aggregators\BestNAggregator;
use App\Standings\Aggregators\SumAggregator;

/**
 * The evaluation system a season's standing is built with.
 */
enum ScoringMode: string
{
    case Standard = 'standard';

    case Zone = 'zone';

    case BestThree = 'best_three';

    case Ruleset = 'ruleset';

    public function aggregator(): Aggregator
    {
        return match ($this) {
            self::Standard  => new SumAggregator(),
            self::Zone      => new BestGroupAggregator(ScoredResult::DIMENSION_REGION),
            self::Ruleset   => new BestGroupAggregator(ScoredResult::DIMENSION_RULESET),
            self::BestThree => new BestNAggregator(3),
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Standard  => 'Standard (alle Punkte addieren)',
            self::Zone      => 'Zonensystem (beste Zone zählt)',
            self::BestThree => 'Best Three (drei beste Turniere)',
            self::Ruleset   => 'Regelwerksystem (bestes Regelwerk zählt)',
        };
    }

    /**
     * @return array<string, string> Keyed by value, for Filament select fields.
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
