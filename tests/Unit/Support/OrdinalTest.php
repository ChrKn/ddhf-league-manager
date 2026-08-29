<?php

namespace Tests\Unit\Support;

use App\Support\Ordinal;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * How a rank is written, per language.
 *
 * Short rules, but the English one has an exception that is easy to get wrong and impossible to
 * notice by looking at a page: eleven, twelve and thirteen take "th" although their last digit says
 * otherwise, and so does every hundred after them. A standing with more than a hundred fencers is
 * not hypothetical here - Langes Schwert offen has passed that mark.
 */
class OrdinalTest extends TestCase
{
    public static function english(): array
    {
        return [
            [1, '1st'], [2, '2nd'], [3, '3rd'], [4, '4th'], [5, '5th'],
            // The exception, and the digits it overrules.
            [11, '11th'], [12, '12th'], [13, '13th'],
            [21, '21st'], [22, '22nd'], [23, '23rd'], [24, '24th'],
            [100, '100th'], [101, '101st'], [102, '102nd'], [103, '103rd'],
            // The exception repeats every hundred, which the last digit alone would miss.
            [111, '111th'], [112, '112th'], [113, '113th'],
        ];
    }

    #[DataProvider('english')]
    public function test_english_appends_the_suffix_the_last_two_digits_call_for(int $number, string $expected): void
    {
        $this->assertSame($expected, Ordinal::format($number, 'en'));
    }

    public function test_german_puts_a_full_stop_behind_the_number(): void
    {
        foreach ([1, 2, 3, 11, 21, 111] as $number) {
            $this->assertSame("{$number}.", Ordinal::format($number, 'de'));
        }
    }

    public function test_german_is_what_a_caller_gets_without_asking(): void
    {
        // The default is not convenience: the API hands ranks out and has always handed them out
        // in German, and a caller that forgets to name a language must not change that.
        $this->assertSame('2.', Ordinal::format(2));
        $this->assertSame('2.', Ordinal::format(2, 'fr'));
    }
}
