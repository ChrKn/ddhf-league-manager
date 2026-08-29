<?php

namespace Tests\Unit\Standings;

use App\Standings\Placement;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The vocabulary of the points table, and the one place it is written down. Everything that reads
 * a result - the evaluator, the importer, the admin panel, the API - goes through here, so a
 * spelling that slips past this class slips past all of them.
 */
class PlacementTest extends TestCase
{
    public function test_a_number_is_the_rank_someone_finished_on(): void
    {
        $placement = Placement::from('17');

        $this->assertTrue($placement->isRank());
        $this->assertSame(17, $placement->rank);
        $this->assertNull($placement->round_size);
        $this->assertSame('17.', $placement->label());
    }

    public function test_a_round_says_how_many_were_still_in(): void
    {
        $placement = Placement::from('last-16');

        $this->assertTrue($placement->isRound());
        $this->assertSame(16, $placement->round_size);
        $this->assertNull($placement->rank);
        // The name the official table uses, not one invented here.
        $this->assertSame('8tel Finale', $placement->label());
    }

    public function test_a_round_the_table_does_not_name_by_a_final_keeps_its_size(): void
    {
        $this->assertSame('6er Runde', Placement::from('last-6')->label());
        $this->assertSame('24er Runde', Placement::from('last-24')->label());
    }

    public function test_english_changes_the_rank_and_leaves_the_round_names_alone(): void
    {
        // Two different things wear the same label. A rank is number formatting, and German
        // notation in an English sentence is wrong; a round name is the table's own heading, and
        // what it is called in English is the federation's word and not ours to invent.
        $this->assertSame('17th', Placement::from('17')->label('en'));
        $this->assertSame('1st', Placement::from('1')->label('en'));

        $this->assertSame('8tel Finale', Placement::from('last-16')->label('en'));
        $this->assertSame('6er Runde', Placement::from('last-6')->label('en'));
        $this->assertSame('Vorrunde', Placement::from('pools')->label('en'));
    }

    public function test_the_pool_exit_is_its_own_value(): void
    {
        $placement = Placement::from('pools');

        $this->assertTrue($placement->isPools());
        $this->assertFalse($placement->isRank());
        $this->assertFalse($placement->isRound());
        $this->assertSame('Vorrunde', $placement->label());
    }

    public function test_a_round_the_table_has_no_column_for_is_still_a_round(): void
    {
        // The Symphony of Steel 2025 had a round of eighteen. The evaluator rounds it up to the
        // next column; recording it has to work first.
        $this->assertSame(18, Placement::from('last-18')->round_size);
    }

    public function test_the_last_four_may_not_be_recorded_as_a_round(): void
    {
        // Third and fourth both go out in the round of four and score differently, so the table
        // gives that round no column. Writing last-4 would be a modelling error, not a typo.
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Platzierung/');

        Placement::from('last-4');
    }

    public static function sourceProvider(): array
    {
        return [
            // What the Berlin HEMA Cup 2025 sheet writes into its placement column.
            'Berlin, quarter final' => ['4tel Finale', 'last-8'],
            'Berlin, eighth final'  => ['8tel Finale', 'last-16'],
            'Berlin, sixteenth'     => ['16tel Finale', 'last-32'],
            'Berlin, pools'         => ['Pools', 'pools'],
            // What the Dresdner Fechtschul 2025 bout sheet writes into its phase column.
            'Dresden, top 16'       => ['Top 16', 'last-16'],
            'Dresden, top 8'        => ['Top 8', 'last-8'],
            // The columns the table names by size.
            'by size'               => ['6er Runde', 'last-6'],
            'by size, larger'       => ['48er Runde', 'last-48'],
            // Wording nobody has used yet but everybody might.
            'german quarter'        => ['Viertelfinale', 'last-8'],
            'english round of'      => ['Round of 16', 'last-16'],
            'german preliminary'    => ['Vorrunde', 'pools'],
            // Our own spelling has to survive the trip as well.
            'canonical round'       => ['last-16', 'last-16'],
            'canonical pools'       => ['pools', 'pools'],
            // A rank written the way a spreadsheet formats it.
            'rank with a dot'       => ['1.', '1'],
            'rank with spaces'      => ['  12  ', '12'],
        ];
    }

    #[DataProvider('sourceProvider')]
    public function test_it_takes_the_wording_the_organisers_use(string $written, string $stored): void
    {
        $this->assertSame($stored, (string) Placement::fromSource($written));
    }

    public function test_it_refuses_a_wording_it_cannot_place(): void
    {
        // "Semifinals" is a phase, but which of two people it names depends on the bout result.
        // Guessing would put someone on the wrong side of a four point gap.
        $this->expectException(InvalidArgumentException::class);

        Placement::fromSource('Semifinals');
    }

    public function test_the_error_names_what_is_allowed(): void
    {
        try {
            Placement::from('irgendwas');
            $this->fail('Expected an exception.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('pools', $exception->getMessage());
            $this->assertStringContainsString('last-8', $exception->getMessage());
        }
    }

    public function test_it_orders_a_result_list_the_way_the_table_reads(): void
    {
        // The Hanseschlag 2025 open field, in the order its page has to show.
        $values = ['pools', 'last-16', '2', 'last-8', '1', '4', '3'];
        usort($values, fn ($a, $b) => Placement::from($a)->sortKey() <=> Placement::from($b)->sortKey());

        $this->assertSame(['1', '2', '3', '4', 'last-8', 'last-16', 'pools'], $values);
    }

    public function test_describes_answers_without_throwing(): void
    {
        $this->assertTrue(Placement::describes('Pools'));
        $this->assertTrue(Placement::describes('12'));
        $this->assertFalse(Placement::describes('zurückgezogen'));
    }
}
