<?php

namespace Tests\Feature\Console;

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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The report that finds who owes a category declaration.
 *
 * Its point is to be believed: it says a missing declaration costs somebody their place in a
 * standing, and somebody then goes looking for that declaration. A case that costs nothing must
 * therefore not be reported as one - which is what happens when "is ranked at all" is asked as
 * "has any federation" rather than as "has ours".
 */
class DivisionChoiceReportTest extends TestCase
{
    use RefreshDatabase;

    private Season $offen;

    private Season $damen;

    private Federation $own;

    private Federation $foreign;

    private Group $club;

    protected function setUp(): void
    {
        parent::setUp();

        $this->own = Federation::create([
            'name'         => 'Deutscher Dachverband für historisches Fechten e. V.',
            'abbreviation' => Federation::OWN,
            'is_active'    => true,
        ]);

        $this->foreign = Federation::create([
            'name'         => 'Polska Federacja Dawnych Europejskich Sztuk Walki',
            'abbreviation' => 'PFDESW',
            'is_active'    => true,
        ]);

        $this->club = Group::create(['name' => 'Schildwache Potsdam', 'is_active' => true]);

        $matrix = ScoringMatrix::create([
            'name'   => 'Punkteschlüssel Test',
            'matrix' => json_encode([[
                'participants' => ['min' => 1],
                'points'       => [['place' => ['min' => 1, 'points' => 10]]],
            ]]),
        ]);

        $discipline = Discipline::create(['name' => 'Rapier']);

        $this->offen = $this->season($discipline, 'offen', $matrix);
        $this->damen = $this->season($discipline, 'Damen+', $matrix);

        $this->offen->update(['division_choice_required' => true]);
        $this->damen->update(['division_choice_required' => true]);
    }

    private function season(Discipline $discipline, string $division, ScoringMatrix $matrix): Season
    {
        return Season::create([
            'year'              => 2025,
            'standing_id'       => Standing::create([
                'discipline_id' => $discipline->id,
                'division_id'   => Division::create(['name' => $division])->id,
            ])->id,
            'scoring_matrix_id' => $matrix->id,
            'scoring_mode'      => 'standard',
        ]);
    }

    private function fencer(string $first): Fencer
    {
        return Fencer::create([
            'first_name' => $first,
            'last_name'  => 'Fechterin',
            'is_active'  => true,
            'group_id'   => $this->club->id,
        ]);
    }

    private function placed(Season $season, Fencer $fencer, ?Federation $federation, string $request = ''): void
    {
        $tournament = Tournament::create([
            'season_id'         => $season->id,
            'event_id'          => Event::create(['name' => 'Dresdner Fechtschul', 'start_date' => '2025-10-26'])->id,
            'participant_count' => 20,
        ]);

        Result::create([
            'tournament_id'      => $tournament->id,
            'fencer_id'          => $fencer->id,
            'group_id'           => $this->club->id,
            'federation_id'      => $federation?->id,
            'counted_on_request' => $request ?: null,
            'placement'          => '1',
        ]);
    }

    /** Both categories, no declaration - the shape the report exists for. */
    private function undeclared(string $first, ?Federation $federation, string $request = ''): Fencer
    {
        $fencer = $this->fencer($first);
        $this->placed($this->offen, $fencer, $federation, $request);
        $this->placed($this->damen, $fencer, $federation);

        return $fencer;
    }

    public function test_a_fencer_of_a_foreign_federation_costs_nothing_and_raises_no_alarm(): void
    {
        $this->undeclared('Fremde', $this->foreign);

        $this->artisan('standings:division-choices', ['--year' => 2025])
            ->expectsOutputToContain('0 davon betreffen den eigenen Verband')
            ->doesntExpectOutputToContain('KEINER der beiden Ranglisten')
            ->assertSuccessful();
    }

    public function test_a_fencer_of_the_own_federation_is_reported_as_the_case_it_is(): void
    {
        $this->undeclared('Eigene', $this->own);

        $this->artisan('standings:division-choices', ['--year' => 2025])
            ->expectsOutputToContain('1 davon betrifft den eigenen Verband')
            ->expectsOutputToContain('KEINER der beiden Ranglisten')
            ->assertSuccessful();
    }

    public function test_a_result_counted_on_request_puts_somebody_in_a_standing_too(): void
    {
        // No federation on the result, and yet she is ranked - so the declaration she owes is
        // worth just as much as anybody else's.
        $this->undeclared('Kulanz', null, 'Antrag gestellt, vom Verband zugestimmt.');

        $this->artisan('standings:division-choices', ['--year' => 2025])
            ->expectsOutputToContain('1 davon betrifft den eigenen Verband')
            ->assertSuccessful();
    }

    public function test_a_season_without_the_requirement_is_listed_but_not_warned_about(): void
    {
        $this->offen->update(['division_choice_required' => false]);
        $this->damen->update(['division_choice_required' => false]);
        $this->undeclared('Eigene', $this->own);

        $this->artisan('standings:division-choices', ['--year' => 2025])
            ->expectsOutputToContain('1 Fall, davon 1 ohne hinterlegte Wahl.')
            ->doesntExpectOutputToContain('KEINER der beiden Ranglisten')
            ->assertSuccessful();
    }
}
