<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Groups\Pages\ListGroups;
use App\Filament\Resources\Results\Pages\ListResults;
use App\Filament\Resources\Seasons\Pages\ListSeasons;
use App\Filament\Resources\Tournaments\Pages\ListTournaments;
use App\Models\Discipline;
use App\Models\Division;
use App\Models\Event;
use App\Models\Federation;
use App\Models\Fencer;
use App\Models\Group;
use App\Models\Result;
use App\Models\ScoringMatrix;
use App\Models\Season;
use App\Models\Standing;
use App\Models\Tournament;
use App\Standings\ScoringMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Columns that show an accessor rather than a column of the table.
 *
 * `display_name` is assembled in PHP out of two or three tables. Left to itself, Filament puts the
 * name straight into the SQL and the query dies on a column that does not exist - so searching and
 * sorting have to be aimed at the real ones underneath.
 *
 * This is the sort of thing that fails only when somebody clicks a header, which is why it is
 * worth a test rather than a look. The same fault has been fixed here once before, in the
 * tournament list.
 */
class AccessorColumnsTest extends TestCase
{
    use RefreshDatabase;

    private Group $club;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsMaintainer();

        $ddhf = Federation::create([
            'name'         => 'Deutscher Dachverband für historisches Fechten e. V.',
            'abbreviation' => Federation::OWN,
            'is_active'    => true,
        ]);

        $this->club = Group::create(['name' => 'Schildwache Potsdam', 'is_active' => true]);
        $this->club->federations()->attach($ddhf);

        $matrix = ScoringMatrix::create([
            'name'   => 'Test',
            'matrix' => json_encode([[
                'participants' => ['min' => 0],
                'points'       => [['place' => ['min' => 1, 'points' => 10]]],
            ]]),
        ]);

        $longsword = Discipline::create(['name' => 'Langes Schwert']);
        $sabre = Discipline::create(['name' => 'Säbel']);
        $open = Division::create(['name' => 'offen']);

        foreach ([[$longsword, 2025, 'Dürer Turnier'], [$sabre, 2026, 'Hanseschlag']] as [$discipline, $year, $event]) {
            $season = Season::create([
                'year'              => $year,
                'standing_id'       => Standing::create([
                    'discipline_id' => $discipline->id,
                    'division_id'   => $open->id,
                ])->id,
                'scoring_matrix_id' => $matrix->id,
                'scoring_mode'      => ScoringMode::Standard,
            ]);

            $tournament = Tournament::create([
                'season_id'         => $season->id,
                'event_id'          => Event::create(['name' => $event, 'start_date' => "{$year}-05-09"])->id,
                'participant_count' => 20,
            ]);

            Result::create([
                'tournament_id' => $tournament->id,
                'fencer_id'     => Fencer::create([
                    'first_name' => $year === 2025 ? 'Anna' : 'Berta',
                    'last_name'  => $year === 2025 ? 'Zuletzt' : 'Amsel',
                    'is_active'  => true,
                    'group_id'   => $this->club->id,
                ])->id,
                'group_id'      => $this->club->id,
                'federation_id' => $ddhf->id,
                'placement'     => '1',
            ]);
        }
    }

    public function test_results_can_be_searched_by_tournament(): void
    {
        Livewire::test(ListResults::class)
            ->searchTable('Dürer')
            ->assertCanSeeTableRecords(Result::whereHas('tournament.event', fn ($q) => $q->where('name', 'Dürer Turnier'))->get())
            ->assertCanNotSeeTableRecords(Result::whereHas('tournament.event', fn ($q) => $q->where('name', 'Hanseschlag'))->get());
    }

    public function test_results_can_be_searched_by_the_weapon_in_the_tournament_column(): void
    {
        // The column shows "Säbel offen 2026 (Hanseschlag)", so every word in it should find it.
        Livewire::test(ListResults::class)
            ->searchTable('Säbel')
            ->assertCanSeeTableRecords(Result::whereHas('tournament.season.standing.discipline', fn ($q) => $q->where('name', 'Säbel'))->get());
    }

    public function test_results_can_be_searched_by_year(): void
    {
        Livewire::test(ListResults::class)
            ->searchTable('2025')
            ->assertCanSeeTableRecords(Result::whereHas('tournament.season', fn ($q) => $q->where('year', 2025))->get())
            ->assertCanNotSeeTableRecords(Result::whereHas('tournament.season', fn ($q) => $q->where('year', 2026))->get());
    }

    public function test_results_can_be_searched_by_fencer(): void
    {
        Livewire::test(ListResults::class)
            ->searchTable('Zuletzt')
            ->assertCanSeeTableRecords(Result::whereHas('fencer', fn ($q) => $q->where('last_name', 'Zuletzt'))->get())
            ->assertCanNotSeeTableRecords(Result::whereHas('fencer', fn ($q) => $q->where('last_name', 'Amsel'))->get());
    }

    public function test_results_sort_by_fencer_by_the_name_underneath(): void
    {
        // Amsel before Zuletzt. Sorted on the accessor it would have been an SQL error, not a
        // different order.
        Livewire::test(ListResults::class)
            ->sortTable('fencer.display_name')
            ->assertCanSeeTableRecords(
                Result::query()->join('fencers', 'fencers.id', '=', 'results.fencer_id')
                    ->orderBy('fencers.last_name')->select('results.*')->get(),
                inOrder: true,
            );
    }

    public function test_results_sort_by_tournament_chronologically(): void
    {
        Livewire::test(ListResults::class)
            ->sortTable('tournament.display_name')
            ->assertCanSeeTableRecords(
                Result::query()
                    ->join('tournaments', 'tournaments.id', '=', 'results.tournament_id')
                    ->join('events', 'events.id', '=', 'tournaments.event_id')
                    ->orderBy('events.start_date')->select('results.*')->get(),
                inOrder: true,
            );
    }

    public function test_tournaments_can_be_searched_and_sorted_by_season(): void
    {
        Livewire::test(ListTournaments::class)
            ->searchTable('Säbel')
            ->assertCanSeeTableRecords(Tournament::whereHas('season.standing.discipline', fn ($q) => $q->where('name', 'Säbel'))->get())
            ->assertCanNotSeeTableRecords(Tournament::whereHas('season.standing.discipline', fn ($q) => $q->where('name', 'Langes Schwert'))->get());

        Livewire::test(ListTournaments::class)
            ->sortTable('season.display_name')
            ->assertCanSeeTableRecords(
                Tournament::query()->join('seasons', 'seasons.id', '=', 'tournaments.season_id')
                    ->orderBy('seasons.year')->select('tournaments.*')->get(),
                inOrder: true,
            );
    }

    public function test_seasons_can_be_searched_and_sorted_by_standing(): void
    {
        Livewire::test(ListSeasons::class)
            ->searchTable('Säbel')
            ->assertCanSeeTableRecords(Season::whereHas('standing.discipline', fn ($q) => $q->where('name', 'Säbel'))->get());

        Livewire::test(ListSeasons::class)
            ->sortTable('standing.display_name')
            ->assertSuccessful();
    }

    public function test_clubs_can_be_searched_by_federation(): void
    {
        Livewire::test(ListGroups::class)
            ->searchTable('DDHF')
            ->assertCanSeeTableRecords([$this->club]);
    }

    public function test_the_federation_column_stays_unsortable(): void
    {
        // A club can be in several, so there is no honest answer to which one a row sorts by. The
        // header offers nothing rather than picking one silently.
        $column = Livewire::test(ListGroups::class)
            ->instance()
            ->getTable()
            ->getColumn('federations.display_name');

        $this->assertFalse($column->isSortable());
        $this->assertTrue($column->isSearchable());
    }
}
