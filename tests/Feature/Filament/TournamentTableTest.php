<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Tournaments\Pages\ListTournaments;
use App\Models\Discipline;
use App\Models\Division;
use App\Models\Event;
use App\Models\ScoringMatrix;
use App\Models\Season;
use App\Models\Standing;
use App\Models\Tournament;
use App\Models\User;
use App\Standings\ScoringMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Searching and sorting the tournament list in the admin panel.
 *
 * The column shows Event::display_name - "Dürer Turnier (2019)", an accessor built from the name
 * and the year. Marked searchable and sortable by column name, Filament put "display_name"
 * straight into the SQL, and both threw: unknown column 'display_name' in 'where clause'.
 *
 * These go through the Livewire component rather than the query, because the query was never the
 * part that was wrong - the wiring was.
 */
class TournamentTableTest extends TestCase
{
    use RefreshDatabase;

    private Season $season;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::create([
            'name'     => 'Prüferin',
            'email'    => 'pruefung@example.test',
            'password' => 'geheim',
        ]));

        $this->season = Season::create([
            'year'              => 2025,
            'standing_id'       => Standing::create([
                'discipline_id' => Discipline::create(['name' => 'Langes Schwert'])->id,
                'division_id'   => Division::create(['name' => 'offen'])->id,
            ])->id,
            'scoring_matrix_id' => ScoringMatrix::create([
                'name'   => 'Test',
                'matrix' => json_encode([['participants' => ['min' => 0], 'points' => [['place' => ['min' => 1, 'points' => 1]]]]]),
            ])->id,
            'scoring_mode' => ScoringMode::Standard,
        ]);
    }

    private function tournamentAt(string $eventName, string $date): Tournament
    {
        return Tournament::create([
            'season_id'         => $this->season->id,
            'event_id'          => Event::create(['name' => $eventName, 'start_date' => $date])->id,
            'participant_count' => 4,
        ]);
    }

    public function test_the_list_can_be_searched_by_the_event(): void
    {
        $berlin = $this->tournamentAt('Musterstadt HEMA Cup', '2025-03-01');
        $other = $this->tournamentAt('Probehausen Fechtschul', '2025-06-01');

        Livewire::test(ListTournaments::class)
            ->searchTable('Musterstadt')
            ->assertCanSeeTableRecords([$berlin])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_the_year_shown_in_that_column_can_be_searched_for(): void
    {
        $old = $this->tournamentAt('Musterstadt HEMA Cup', '2025-03-01');

        $newer = Tournament::create([
            'season_id'         => $this->season->id,
            'event_id'          => Event::create(['name' => 'Musterstadt HEMA Cup', 'start_date' => '2019-03-01'])->id,
            'participant_count' => 4,
        ]);

        Livewire::test(ListTournaments::class)
            ->searchTable('2019')
            ->assertCanSeeTableRecords([$newer])
            ->assertCanNotSeeTableRecords([$old]);
    }

    public function test_the_list_can_be_sorted_by_the_event(): void
    {
        $z = $this->tournamentAt('Zornhau Turnier', '2025-03-01');
        $a = $this->tournamentAt('Ansetzen Turnier', '2025-06-01');

        Livewire::test(ListTournaments::class)
            ->sortTable('event.display_name')
            ->assertCanSeeTableRecords([$a, $z], inOrder: true)
            ->sortTable('event.display_name', 'desc')
            ->assertCanSeeTableRecords([$z, $a], inOrder: true);
    }

    public function test_a_search_that_looks_like_nothing_still_answers(): void
    {
        $this->tournamentAt('Musterstadt HEMA Cup', '2025-03-01');

        // The guard around the year clause: YEAR(start_date) = 'Berlin' is a comparison the
        // database should never be asked to make.
        Livewire::test(ListTournaments::class)
            ->searchTable('Berlin')
            ->assertCanNotSeeTableRecords(Tournament::all());
    }
}
