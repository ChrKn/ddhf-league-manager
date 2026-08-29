<?php

namespace Tests\Feature\Standings;

use App\Models\ApiKey;
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
 * A standing is the DDHF's ranking, so it lists members only. Which club someone fenced for is
 * read off the result and not off the person: the club at the person is a present-day value, and
 * using it would let a club change rewrite placements that have already happened - the very fault
 * the old WordPress site had.
 */
class MembershipTest extends TestCase
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
                'discipline_id' => Discipline::create(['name' => 'Langes Schwert'])->id,
                'division_id'   => Division::create(['name' => 'offen'])->id,
            ])->id,
            'scoring_matrix_id' => $matrix->id,
            'scoring_mode'      => ScoringMode::Standard,
        ]);

        $this->tournament = Tournament::create([
            'season_id'         => $this->season->id,
            'event_id'          => Event::create([
                'name'       => 'Testturnier',
                'start_date' => '2026-05-09',
            ])->id,
            'participant_count' => 3,
        ]);
    }

    private function club(string $name, ?Federation $federation): Group
    {
        $club = Group::create([
            'name'      => $name,
            'is_active' => true,
        ]);

        // A club with no federation at all is one of the cases under test, so this stays optional.
        if ($federation !== null) {
            $club->federations()->attach($federation);
        }

        return $club;
    }

    private function fencer(string $first, string $last, ?Group $group = null): Fencer
    {
        return Fencer::create([
            'first_name' => $first,
            'last_name'  => $last,
            'is_active'  => true,
            'group_id'   => $group?->id,
        ]);
    }

    /** Writes a result the way ApplyResultImport does: the club goes on the result. */
    private function addResult(Fencer $fencer, int $placement, ?Group $group): Result
    {
        return Result::create([
            'tournament_id'     => $this->tournament->id,
            'fencer_id'         => $fencer->id,
            'group_id'          => $group?->id,
            'federation_id'     => $group?->scoringFederation()?->id,
            'placement'         => $placement,
            'fencer_group_name' => $group?->name,
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function standing(): array
    {
        $season = Season::with(['tournaments.results.fencer.group', 'tournaments.ruleset'])
            ->find($this->season->id);

        $evaluator = ScoringMatrixEvaluator::fromJson(
            ScoringMatrix::find($this->season->scoring_matrix_id)->matrix
        );

        return (new StandingCalculator($evaluator, ScoringMode::Standard))->calculate($season);
    }

    /** @return list<string> */
    private function names(): array
    {
        return array_map(fn ($entry) => $entry['fencer']['display_name'], $this->standing());
    }

    public function test_a_fencer_of_a_foreign_club_is_absent_from_the_standing(): void
    {
        $this->addResult($this->fencer('Theo', 'Probstett'), 1, $this->club('Ochs', $this->ddhf));
        $this->addResult($this->fencer('Lina', 'Musterhain'), 2, $this->club('Sprezzatura', $this->abroad));

        $this->assertSame(['Theo Probstett'], $this->names());
    }

    public function test_a_club_without_any_federation_does_not_count_either(): void
    {
        // Clubs the import creates from a file that does not mark them as members get no
        // federation at all. That is not "unknown", it is "not a member".
        $this->addResult($this->fencer('Theo', 'Probstett'), 1, $this->club('Ochs', $this->ddhf));
        $this->addResult($this->fencer('Jane', 'Doe'), 2, $this->club('Some HEMA Club', null));

        $this->assertSame(['Theo Probstett'], $this->names());
    }

    public function test_a_result_without_a_club_does_not_count(): void
    {
        $this->addResult($this->fencer('Theo', 'Probstett'), 1, $this->club('Ochs', $this->ddhf));
        $this->addResult($this->fencer('Vera', 'Musterstett'), 2, null);

        $this->assertSame(['Theo Probstett'], $this->names());
    }

    public function test_the_club_on_the_result_beats_a_membership_gained_later(): void
    {
        // She fenced for a club that was not a member and joined one afterwards. The placement
        // stays outside the standing - the standing of that season is not rewritten.
        $today = $this->club('Schwert und Bogen', $this->ddhf);
        $back_then = $this->club('Ort Coburg', null);

        $this->addResult($this->fencer('Theo', 'Probstett'), 1, $this->club('Ochs', $this->ddhf));
        $this->addResult($this->fencer('Melanie', 'Vogler', $today), 2, $back_then);

        $this->assertSame(['Theo Probstett'], $this->names());
    }

    public function test_the_club_on_the_result_beats_a_missing_one_at_the_person(): void
    {
        // The mirror image: she fenced for a member club, and no club is recorded at her person
        // at all. The placement counts.
        $this->addResult($this->fencer('Theo', 'Probstett'), 1, $this->club('Ochs', $this->ddhf));
        $this->addResult(
            $this->fencer('Anna', 'Probenhain'),
            2,
            $this->club('Leichtmeisterei Kassel', $this->ddhf),
        );

        $this->assertSame(['Theo Probstett', 'Anna Probenhain'], $this->names());
    }

    public function test_the_remaining_fencers_keep_their_points(): void
    {
        $this->addResult($this->fencer('Theo', 'Probstett'), 1, $this->club('Ochs', $this->ddhf));
        $this->addResult($this->fencer('Lina', 'Musterhain'), 2, $this->club('Sprezzatura', $this->abroad));
        $this->addResult($this->fencer('Kira', 'Musterloh'), 3, $this->club('Twerchhau', $this->ddhf));

        $standing = $this->standing();

        // Points come from participant_count and placement, so dropping the second place must
        // move Kira Musterloh up a rank without changing what she scored.
        $this->assertCount(2, $standing);
        $this->assertSame(10, $standing[0]['points']);
        $this->assertSame(3, $standing[1]['points']);
        $this->assertSame('Kira Musterloh', $standing[1]['fencer']['display_name']);
    }

    public function test_the_tournament_still_shows_the_whole_field(): void
    {
        // A placement is a fact about the tournament. Only the DDHF ranking is members only.
        $this->addResult($this->fencer('Theo', 'Probstett'), 1, $this->club('Ochs', $this->ddhf));
        $this->addResult($this->fencer('Lina', 'Musterhain'), 2, $this->club('Sprezzatura', $this->abroad));

        $key = ApiKey::create(['name' => 'Test', 'key' => str_repeat('M', 64)])->key;

        $payload = $this->withToken($key)
            ->getJson("/api/tournaments/{$this->tournament->public_id}")
            ->assertOk()
            ->json('data.results');

        $names = array_column(array_column($payload, 'fencer'), 'name');

        $this->assertCount(2, $names);
        $this->assertContains('Lina Musterhain', $names);
    }

    public function test_without_its_own_federation_on_record_nothing_is_ranked(): void
    {
        // Bad configuration must not quietly turn into "everybody qualifies".
        $this->addResult($this->fencer('Theo', 'Probstett'), 1, $this->club('Ochs', $this->ddhf));
        $this->ddhf->update(['abbreviation' => 'Etwas anderes']);

        $this->assertSame([], $this->names());
    }
}
