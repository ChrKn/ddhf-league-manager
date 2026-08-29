<?php

namespace Tests\Feature\Standings;

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
use App\Standings\ScoringMatrixEvaluator;
use App\Standings\ScoringMode;
use App\Standings\StandingCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A result says for itself how it is to be scored, and the standing has to honour that.
 *
 * This is the case that made the change necessary. Read as a placement, someone knocked out in the
 * pools of a forty-two fencer field sits on rank seventeen and collects three points for a column
 * they never reached - the Hanseschlag 2025 handed out exactly that. The points table has a
 * thirteenth column for them, worth one point at any field size, and it applies here.
 */
class PlacementReadingTest extends TestCase
{
    use RefreshDatabase;

    private Season $season;

    private Tournament $tournament;

    private Federation $ddhf;

    private Group $club;

    protected function setUp(): void
    {
        parent::setUp();

        // The data browser is part of the admin panel now.
        $this->actingAsMaintainer();

        $this->ddhf = Federation::create([
            'name'         => 'Deutscher Dachverband für historisches Fechten e. V.',
            'abbreviation' => Federation::OWN,
            'is_active'    => true,
        ]);

        $this->club = Group::create([
            'name'          => 'Hammaborg',
            'is_active'     => true,
        ]);

        $this->club->federations()->attach($this->ddhf);

        // The 33-48 band of Punkteschlüssel 2022+, with all three readings of its columns.
        $matrix = ScoringMatrix::create([
            'name'   => 'Punkteschlüssel Test',
            'matrix' => json_encode([[
                'participants' => ['min' => 33],
                'pools'        => 1,
                'points'       => [
                    ['place' => ['min' => 1,  'points' => 22]],
                    ['place' => ['min' => 2,  'points' => 18]],
                    ['place' => ['min' => 3,  'points' => 15]],
                    ['place' => ['min' => 4,  'points' => 12]],
                    ['place' => ['min' => 7,  'points' => 8],  'round' => 'last-8'],
                    ['place' => ['min' => 13, 'points' => 4],  'round' => 'last-16'],
                    ['place' => ['min' => 17, 'points' => 3],  'round' => 'last-24'],
                    ['place' => ['min' => 25, 'points' => 2],  'round' => 'last-32'],
                ],
            ]]),
        ]);

        $this->season = Season::create([
            'year'              => 2025,
            'standing_id'       => Standing::create([
                'discipline_id' => Discipline::create(['name' => 'Langes Schwert'])->id,
                'division_id'   => Division::create(['name' => 'offen'])->id,
            ])->id,
            'scoring_matrix_id' => $matrix->id,
            'scoring_mode'      => ScoringMode::Standard,
        ]);

        $this->tournament = Tournament::create([
            'season_id'         => $this->season->id,
            'event_id'          => Event::create(['name' => 'Hanseschlag', 'start_date' => '2025-09-06'])->id,
            'participant_count' => 42,
            'format'            => 'Turnierbaum mit Vorrunde',
        ]);
    }

    private function placed(string $first, string $placement): Result
    {
        $fencer = Fencer::create([
            'first_name' => $first,
            'last_name'  => 'Fechter',
            'is_active'  => true,
            'group_id'   => $this->club->id,
        ]);

        return Result::create([
            'tournament_id' => $this->tournament->id,
            'fencer_id'     => $fencer->id,
            'group_id'      => $this->club->id,
            'federation_id' => $this->ddhf->id,
            'placement'     => $placement,
        ]);
    }

    /** @return array<string, int> fencer => points */
    private function standing(): array
    {
        $season = $this->season->fresh()->load('tournaments.results.fencer.group', 'tournaments.ruleset');

        $calculator = new StandingCalculator(
            ScoringMatrixEvaluator::fromJson($this->season->scoring_matrix->matrix),
            $this->season->scoring_mode
        );

        $points = [];

        foreach ($calculator->calculate($season) as $entry) {
            $points[$entry['fencer']->display_name] = $entry['points'];
        }

        return $points;
    }

    public function test_a_pool_exit_is_one_point_however_large_the_field(): void
    {
        $this->placed('Vorrunde', 'pools');

        $this->assertSame(1, $this->standing()['Vorrunde Fechter']);
    }

    public function test_the_same_person_read_as_a_rank_would_have_scored_three(): void
    {
        // Not a wish for the old behaviour but a guard on the matrix: if this ever came out as one
        // as well, the test above would be passing for the wrong reason.
        $this->placed('Siebzehnter', '17');

        $this->assertSame(3, $this->standing()['Siebzehnter Fechter']);
    }

    public function test_a_bracket_result_scores_the_round_it_names(): void
    {
        $this->placed('Viertelfinale', 'last-8');
        $this->placed('Achtelfinale', 'last-16');

        $standing = $this->standing();

        $this->assertSame(8, $standing['Viertelfinale Fechter']);
        $this->assertSame(4, $standing['Achtelfinale Fechter']);
    }

    public function test_the_first_four_are_placements_even_in_a_bracket(): void
    {
        // Third and fourth both go out in the round of four and score differently, so the table
        // gives that round no column and these stay numbers.
        $this->placed('Erster', '1');
        $this->placed('Vierter', '4');

        $standing = $this->standing();

        $this->assertSame(22, $standing['Erster Fechter']);
        $this->assertSame(12, $standing['Vierter Fechter']);
    }

    public function test_the_tournament_page_names_the_round_rather_than_the_key(): void
    {
        $this->placed('Achtelfinale', 'last-16');
        $this->placed('Vorrunde', 'pools');

        $this->get(\App\Filament\Pages\Browse\Tournament::getUrl(['public_id' => $this->tournament->public_id]))
            ->assertOk()
            ->assertSee('Achtelfinale')
            ->assertSee('Vorrunde')
            ->assertDontSee('last-16');
    }

    public function test_the_api_orders_rounds_the_way_the_table_reads(): void
    {
        // By the stored string "last-16" would come before "last-8", which would put the fencers
        // who went out earlier above the ones who went further.
        $this->placed('Achtelfinale', 'last-16');
        $this->placed('Erster', '1');
        $this->placed('Vorrunde', 'pools');
        $this->placed('Viertelfinale', 'last-8');

        $payload = (new \App\Http\Resources\TournamentResource(
            $this->tournament->fresh()->load('results.fencer.group', 'season.standing', 'event')
        ))->toArray(\Illuminate\Http\Request::create('/'));

        $this->assertSame(
            ['1', 'last-8', 'last-16', 'pools'],
            array_column($payload['results']->resolve(), 'placement')
        );
    }
}
