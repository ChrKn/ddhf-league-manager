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
 * The Kulanzregelung: on application, the last DDHF tournament somebody took part in before their
 * club joined can be counted.
 *
 * It is the one thing a standing cannot work out for itself. Whether an application was made and
 * approved is not in any date, any club and any result - somebody decided it, and the decision is
 * written on the result. Everything else about membership stays exactly as it was; see
 * MembershipTest, which is the rule this is the exception to.
 */
class CountedOnRequestTest extends TestCase
{
    use RefreshDatabase;

    private Tournament $tournament;

    private Season $season;

    private Federation $ddhf;

    private Federation $abroad;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ddhf = Federation::create([
            'name'         => 'Deutscher Dachverband für historisches Fechten e. V.',
            'abbreviation' => Federation::OWN,
            'is_active'    => true,
        ]);

        $this->abroad = Federation::create([
            'name'         => 'Österreichischer Fachverband für historisches Fechten',
            'abbreviation' => 'ÖFHF',
            'is_active'    => true,
        ]);

        $matrix = ScoringMatrix::create([
            'name'   => 'Test',
            'matrix' => json_encode([[
                'participants' => ['min' => 0],
                'points'       => [
                    ['place' => ['min' => 1, 'points' => 10]],
                    ['place' => ['min' => 2, 'points' => 6]],
                    ['place' => ['min' => 3, 'points' => 3]],
                ],
            ]]),
        ]);

        $this->season = Season::create([
            'year'              => 2026,
            'standing_id'       => Standing::create([
                'discipline_id' => Discipline::create(['name' => 'Rapier'])->id,
                'division_id'   => Division::create(['name' => 'offen'])->id,
            ])->id,
            'scoring_matrix_id' => $matrix->id,
            'scoring_mode'      => ScoringMode::Standard,
        ]);

        $this->tournament = Tournament::create([
            'season_id'         => $this->season->id,
            'event_id'          => Event::create([
                'name'       => 'Testturnier',
                'start_date' => '2026-02-01',
            ])->id,
            'participant_count' => 3,
        ]);
    }

    private function club(string $name, ?Federation $federation): Group
    {
        $club = Group::create(['name' => $name, 'is_active' => true]);

        if ($federation !== null) {
            $club->federations()->attach($federation);
        }

        return $club;
    }

    private function addResult(string $last, int $placement, ?Group $group, ?string $granted = null): Result
    {
        return Result::create([
            'tournament_id'      => $this->tournament->id,
            'fencer_id'          => Fencer::create([
                'first_name' => 'Test',
                'last_name'  => $last,
                'is_active'  => true,
            ])->id,
            'group_id'           => $group?->id,
            'federation_id'      => $group?->scoringFederation()?->id,
            'counted_on_request' => $granted,
            'placement'          => $placement,
            'fencer_group_name'  => $group?->name,
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function standing(): array
    {
        $season = Season::with(['tournaments.results.fencer.group', 'tournaments.ruleset'])
            ->find($this->season->id);

        return (new StandingCalculator(
            ScoringMatrixEvaluator::fromJson(ScoringMatrix::find($this->season->scoring_matrix_id)->matrix),
            ScoringMode::Standard,
        ))->calculate($season);
    }

    /** @return list<string> */
    private function names(): array
    {
        return array_map(fn ($entry) => $entry['fencer']['display_name'], $this->standing());
    }

    public function test_a_granted_exception_counts_although_the_club_was_no_member(): void
    {
        $this->addResult('Erste', 1, $this->club('Ochs', $this->ddhf));
        $this->addResult('Zweite', 2, $this->club('Noch kein Mitglied', null), 'Antrag gestellt, Präsidium hat zugestimmt.');

        $this->assertSame(['Test Erste', 'Test Zweite'], $this->names());
    }

    public function test_the_same_result_without_a_granted_exception_stays_out(): void
    {
        // Der Normalfall, und er ist der wichtigere: ohne Eintrag bleibt es bei der Mitgliedschaft
        // am Turniertag.
        $this->addResult('Erste', 1, $this->club('Ochs', $this->ddhf));
        $this->addResult('Zweite', 2, $this->club('Noch kein Mitglied', null));

        $this->assertSame(['Test Erste'], $this->names());
    }

    public function test_an_empty_string_is_not_a_granted_exception(): void
    {
        $this->addResult('Erste', 1, $this->club('Ochs', $this->ddhf));
        $this->addResult('Zweite', 2, $this->club('Noch kein Mitglied', null), '');

        $this->assertSame(['Test Erste'], $this->names());
    }

    public function test_it_also_covers_someone_who_fenced_for_a_foreign_club(): void
    {
        // Die Regel spricht von "noch kein Mitglied", nicht von "in keinem Verband". Wer für einen
        // ausländischen Verein angetreten ist, kann denselben Antrag stellen.
        $this->addResult('Erste', 1, $this->club('Ochs', $this->ddhf));
        $this->addResult('Zweite', 2, $this->club('Sprezzatura', $this->abroad), 'Antrag gestellt, zugestimmt.');

        $this->assertSame(['Test Erste', 'Test Zweite'], $this->names());
    }

    public function test_the_exception_earns_the_points_the_placement_is_worth(): void
    {
        $this->addResult('Erste', 1, $this->club('Ochs', $this->ddhf));
        $this->addResult('Zweite', 2, $this->club('Noch kein Mitglied', null), 'Antrag gestellt, zugestimmt.');

        $punkte = array_column($this->standing(), 'points');

        $this->assertSame([10, 6], $punkte);
    }

    public function test_it_does_not_reach_beyond_the_one_result_it_is_written_on(): void
    {
        // Die Kulanz gilt für ein Turnier. Ein zweites Ergebnis desselben Vereins bleibt draußen,
        // solange nicht auch dort jemand zugestimmt hat.
        $verein = $this->club('Noch kein Mitglied', null);

        $zweites = Tournament::create([
            'season_id'         => $this->season->id,
            'event_id'          => Event::create(['name' => 'Zweites', 'start_date' => '2026-03-01'])->id,
            'participant_count' => 3,
        ]);

        $this->addResult('Erste', 1, $this->club('Ochs', $this->ddhf));
        $mit = $this->addResult('Zweite', 2, $verein, 'Antrag gestellt, zugestimmt.');

        Result::create([
            'tournament_id'     => $zweites->id,
            'fencer_id'         => $mit->fencer_id,
            'group_id'          => $verein->id,
            'placement'         => 1,
            'fencer_group_name' => $verein->name,
        ]);

        $eintrag = collect($this->standing())->firstWhere('fencer.display_name', 'Test Zweite');

        $this->assertSame(6, $eintrag['points']);
        $this->assertCount(1, $eintrag['results']);
    }
}
