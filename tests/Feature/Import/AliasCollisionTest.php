<?php

namespace Tests\Feature\Import;

use App\Import\GroupResolver;
use App\Import\ImportWriter;
use App\Models\Discipline;
use App\Models\Division;
use App\Models\Event;
use App\Models\Federation;
use App\Models\Group;
use App\Models\GroupAlias;
use App\Models\ScoringMatrix;
use App\Models\Season;
use App\Models\Standing;
use App\Models\Tournament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * One spelling names one club.
 *
 * The alias table has a unique index on the string, but the resolver folds before it compares -
 * lower case, no punctuation, no "e. V." - so "ESK Augsburg" and "ESK-Augsburg e. V." are one key
 * to it and two rows to the database. Nineteen such pairs are already stored and all of them sit
 * on a single club, which is why nothing has gone wrong yet. Split a pair across two clubs and the
 * map the resolver builds keeps whichever row it read last, with nothing said anywhere.
 *
 * These tests are the guard against that, and against its quieter cousin: a spelling stored while
 * another club is *named* that way, which resolve() would never reach because a name beats an
 * alias.
 */
class AliasCollisionTest extends TestCase
{
    use RefreshDatabase;

    private function group(string $name): Group
    {
        return Group::create(['name' => $name, 'is_active' => true]);
    }

    public function test_a_spelling_another_club_holds_is_not_taken_over(): void
    {
        $esk = $this->group('Europäische Schwertkunst');
        $indes = $this->group('INDES Regensburg');
        GroupAlias::create(['group_id' => $esk->id, 'alias' => 'ESK Augsburg']);

        $resolver = new GroupResolver();
        $conflict = $resolver->rememberAlias('ESK Augsburg', $indes);

        $this->assertTrue($conflict?->is($esk), 'Der bisherige Verein muss benannt werden');
        $this->assertSame(1, GroupAlias::count());

        // Both readings agree, which is the point: the running import must not resolve this
        // spelling differently from the next one.
        $this->assertTrue($resolver->resolve('ESK Augsburg')->record?->is($esk));
        $this->assertTrue((new GroupResolver())->resolve('ESK Augsburg')->record?->is($esk));
    }

    public function test_a_different_spelling_of_the_same_key_is_caught_as_well(): void
    {
        $esk = $this->group('Europäische Schwertkunst');
        $indes = $this->group('INDES Regensburg');
        GroupAlias::create(['group_id' => $esk->id, 'alias' => 'ESK Augsburg']);

        // A string the unique index lets through, because it is not the same string.
        $conflict = (new GroupResolver())->rememberAlias('ESK-Augsburg e. V.', $indes);

        $this->assertTrue($conflict?->is($esk));
        $this->assertSame(1, GroupAlias::count());
        $this->assertTrue((new GroupResolver())->resolve('ESK-Augsburg e. V.')->record?->is($esk));
    }

    public function test_a_second_spelling_for_the_same_club_is_still_welcome(): void
    {
        $esk = $this->group('Europäische Schwertkunst');
        GroupAlias::create(['group_id' => $esk->id, 'alias' => 'ESK Augsburg']);

        // Nineteen pairs like this are in the live table. They resolve to one club and are fine.
        $this->assertNull((new GroupResolver())->rememberAlias('ESK-Augsburg e. V.', $esk));
        $this->assertSame(2, GroupAlias::count());
    }

    public function test_a_spelling_that_is_another_clubs_name_is_refused(): void
    {
        $ochs = $this->group('Ochs');
        $indes = $this->group('INDES Regensburg');

        // resolve() checks the club names before the aliases, so this row would be stored and
        // then never read.
        $conflict = (new GroupResolver())->rememberAlias('Ochs e. V.', $indes);

        $this->assertTrue($conflict?->is($ochs));
        $this->assertSame(0, GroupAlias::count());
    }

    public function test_the_import_says_so_and_still_writes_the_results(): void
    {
        $ddhf = Federation::create([
            'name'         => 'Deutscher Dachverband für historisches Fechten e. V.',
            'abbreviation' => Federation::OWN,
            'is_active'    => true,
        ]);

        $esk = $this->group('Europäische Schwertkunst');
        $indes = $this->group('INDES Regensburg');
        $indes->federations()->attach($ddhf);
        GroupAlias::create(['group_id' => $esk->id, 'alias' => 'ESK Augsburg']);

        $tournament = $this->tournament();

        $rows = [];

        // Twice, because a file names a club once per fencer and the message must not repeat.
        foreach (['Erste', 'Zweite'] as $index => $name) {
            $rows[] = [
                'aktion'       => 'create',
                'name_datei'   => "{$name} Person",
                'fechter_id'   => '',
                'verein_datei' => 'ESK Augsburg',
                'verein_id'    => $indes->public_id,
                'turnier'      => $tournament->public_id,
                'platz'        => (string) ($index + 1),
            ];
        }

        $summary = (new ImportWriter(rememberAliases: true))->write($rows);

        // The file's decision stands for this file - the results belong to the club the review
        // screen picked - but the alias table is untouched and the reviewer is told.
        $this->assertSame(2, $summary->results);
        $this->assertSame(1, GroupAlias::count());
        $this->assertCount(1, $summary->aliasConflicts);
        $this->assertStringContainsString('Europäische Schwertkunst', $summary->aliasConflicts[0]);
        $this->assertStringContainsString('INDES Regensburg', $summary->aliasConflicts[0]);
        $this->assertSame([], $summary->aliases);
    }

    private function tournament(): Tournament
    {
        $season = Season::create([
            'year'        => 2026,
            'standing_id' => Standing::create([
                'discipline_id' => Discipline::create(['name' => 'Langes Schwert'])->id,
                'division_id'   => Division::create(['name' => 'offen'])->id,
            ])->id,
            'scoring_matrix_id' => ScoringMatrix::create([
                'name'   => 'Test',
                'matrix' => json_encode([[
                    'participants' => ['min' => 0],
                    'points'       => [['place' => ['min' => 1, 'points' => 10]]],
                ]]),
            ])->id,
        ]);

        return Tournament::create([
            'season_id'         => $season->id,
            'event_id'          => Event::create(['name' => 'Musterturnier', 'start_date' => '2026-05-09'])->id,
            'participant_count' => 20,
        ]);
    }
}
