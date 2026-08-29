<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Seasons\Pages\EditSeason;
use App\Filament\Resources\Seasons\RelationManagers\FencersRelationManager;
use App\Models\Discipline;
use App\Models\Division;
use App\Models\Fencer;
use App\Models\Group;
use App\Models\ScoringMatrix;
use App\Models\Season;
use App\Models\Standing;
use App\Models\User;
use App\Standings\ScoringMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Entering who is ranked in a season, from the season's own page.
 *
 * The list was written and never opened by a test, and it threw the first time somebody clicked
 * through to it: the label of the attach select was being set with a method that belongs to the
 * select and not to the action, which only comes apart when the table is built. Building the table
 * is what the first test here does, and it is the one that would have caught it.
 */
class SeasonFencersPanelTest extends TestCase
{
    use RefreshDatabase;

    private Season $season;

    private Group $club;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::create([
            'name'     => 'Prüferin',
            'email'    => 'pruefung@example.test',
            'password' => 'geheim',
        ]));

        $this->club = Group::create(['name' => 'Schildwache Potsdam', 'is_active' => true]);

        $this->season = Season::create([
            'year'              => 2026,
            'standing_id'       => Standing::create([
                'discipline_id' => Discipline::create(['name' => 'Langes Schwert'])->id,
                'division_id'   => Division::create(['name' => 'Damen+'])->id,
            ])->id,
            'scoring_matrix_id' => ScoringMatrix::create([
                'name'   => 'Test',
                'matrix' => json_encode([[
                    'participants' => ['min' => 0],
                    'points'       => [['place' => ['min' => 1, 'points' => 1]]],
                ]]),
            ])->id,
            'scoring_mode' => ScoringMode::Standard,
        ]);
    }

    private function fencer(string $first, string $last, bool $inClub = true): Fencer
    {
        return Fencer::create([
            'first_name' => $first,
            'last_name'  => $last,
            'is_active'  => true,
            'group_id'   => $inClub ? $this->club->id : null,
        ]);
    }

    private function manager(): \Livewire\Features\SupportTesting\Testable
    {
        return Livewire::test(FencersRelationManager::class, [
            'ownerRecord' => $this->season,
            'pageClass'   => EditSeason::class,
        ]);
    }

    public function test_the_list_opens(): void
    {
        $this->manager()->assertSuccessful();
    }

    public function test_it_shows_who_is_entered(): void
    {
        $entered = $this->fencer('Test', 'Eins');
        $this->fencer('Test', 'Zwei');

        $this->season->fencers()->attach($entered);

        $this->manager()
            ->assertCanSeeTableRecords([$entered])
            ->assertCanRenderTableColumn('display_name')
            ->assertCanRenderTableColumn('group.name');
    }

    public function test_somebody_can_be_entered_and_taken_out_again(): void
    {
        $fencer = $this->fencer('Test', 'Eins');

        $this->manager()->callTableAction('attach', data: ['recordId' => $fencer->getKey()]);

        $this->assertTrue($this->season->fencers()->whereKey($fencer->getKey())->exists());

        $this->manager()->callTableAction('detach', $fencer);

        $this->assertFalse($this->season->fencers()->whereKey($fencer->getKey())->exists());
    }

    public function test_the_club_is_part_of_the_name_to_choose_from(): void
    {
        $fencer = $this->fencer('Test', 'Eins');

        // Whoever needs an entry here fenced both categories of one weapon, and two of those can
        // share a name - the club is what tells them apart in a list of nothing but names.
        $this->assertSame(
            'Test Eins (Schildwache Potsdam)',
            $this->manager()
                ->instance()
                ->getTable()
                ->getAction('attach')
                ->getRecordTitle($fencer),
        );
    }

    public function test_a_fencer_without_a_club_still_has_a_readable_name(): void
    {
        $fencer = $this->fencer('Test', 'Zwei', inClub: false);

        $this->assertSame(
            'Test Zwei',
            $this->manager()
                ->instance()
                ->getTable()
                ->getAction('attach')
                ->getRecordTitle($fencer),
        );
    }
}
