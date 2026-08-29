<?php

namespace Tests\Feature\Standings;

use App\Filament\Resources\Fencers\Actions\AnonymizeFencerAction;
use App\Http\Resources\ResultResource;
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
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * Anonymising removes the person, not the placement.
 *
 * The record keeps nothing - not even the name, which is why the placeholder is derived in the
 * code. The result keeps its placement and its federation, so the entry stays in the standing at
 * the rank it was fenced to and nobody behind it moves up. What must never survive is a way back
 * to a person: no name, no club, no id the API would resolve.
 */
class AnonymizationTest extends TestCase
{
    use RefreshDatabase;

    private Tournament $tournament;

    private Season $season;

    private Federation $ddhf;

    protected function setUp(): void
    {
        parent::setUp();

        // The standing only ranks members, so everyone here is one - otherwise these tests
        // would be measuring the membership filter instead of anonymisation.
        $this->ddhf = Federation::create([
            'name'         => 'Deutscher Dachverband für historisches Fechten e. V.',
            'abbreviation' => Federation::OWN,
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

        $standing = Standing::create([
            'discipline_id' => Discipline::create(['name' => 'Langes Schwert'])->id,
            'division_id'   => Division::create(['name' => 'offen'])->id,
        ]);

        $this->season = Season::create([
            'year'              => 2026,
            'standing_id'       => $standing->id,
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

    private function fencer(string $first, string $last): Fencer
    {
        return Fencer::create([
            'first_name' => $first,
            'last_name'  => $last,
            'is_active'  => true,
            'group_id'   => Group::create([
                'name'          => "Verein {$last}",
                'is_active'     => true,
                'federation_id' => $this->ddhf->id,
            ])->id,
        ]);
    }

    private function addResult(Fencer $fencer, int $placement): Result
    {
        return Result::create([
            'tournament_id' => $this->tournament->id,
            'fencer_id'     => $fencer->id,
            'group_id'      => $fencer->group_id,
            'federation_id' => $this->ddhf->id,
            'placement'     => $placement,
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

    public function test_an_anonymized_fencer_keeps_their_place_in_the_standing(): void
    {
        $this->addResult($this->fencer('Max', 'Musterlein'), 1);
        $this->addResult($anonymous = $this->fencer('Erika', 'Mustermann'), 2);
        AnonymizeFencerAction::anonymize($anonymous);

        $standing = $this->standing();
        $names = array_map(fn ($entry) => $entry['fencer']['display_name'], $standing);

        // Second place is still second place. Dropping the row would hand the runner-up spot to
        // whoever came third, which is a false statement about a season that has been fenced.
        $this->assertSame(['Max Musterlein', Fencer::ANONYMOUS_NAME], $names);
        $this->assertSame(6, $standing[1]['points']);
        $this->assertNull($standing[1]['group']);
    }

    public function test_the_other_fencers_keep_their_points_and_ranks(): void
    {
        $this->addResult($this->fencer('Max', 'Musterlein'), 1);
        $this->addResult($anonymous = $this->fencer('Erika', 'Mustermann'), 2);
        $this->addResult($this->fencer('Otto', 'Probstett'), 3);

        $before = $this->standing();
        AnonymizeFencerAction::anonymize($anonymous);
        $after = $this->standing();

        // Nobody moves: the row stays where it was and only loses its name.
        $this->assertCount(3, $before);
        $this->assertCount(3, $after);
        $this->assertSame(10, $after[0]['points']);
        $this->assertSame('Max Musterlein', $after[0]['fencer']['display_name']);
        $this->assertSame(6, $after[1]['points']);
        $this->assertSame(Fencer::ANONYMOUS_NAME, $after[1]['fencer']['display_name']);
        $this->assertSame(3, $after[2]['points']);
        $this->assertSame('Otto Probstett', $after[2]['fencer']['display_name']);
    }

    public function test_several_results_of_one_anonymized_fencer_still_aggregate_into_one_entry(): void
    {
        $second = Tournament::create([
            'season_id'         => $this->season->id,
            'event_id'          => Event::create([
                'name'       => 'Zweites Testturnier',
                'start_date' => '2026-06-09',
            ])->id,
            'participant_count' => 3,
        ]);

        $anonymous = $this->fencer('Erika', 'Mustermann');
        $this->addResult($anonymous, 2);
        Result::create([
            'tournament_id' => $second->id,
            'fencer_id'     => $anonymous->id,
            'federation_id' => $this->ddhf->id,
            'placement'     => 1,
        ]);

        AnonymizeFencerAction::anonymize($anonymous);
        $standing = $this->standing();

        // Grouping is by fencer_id, not by name, so the placeholder cannot merge two people or
        // split one across two rows.
        $this->assertCount(1, $standing);
        $this->assertCount(2, $standing[0]['results']);
        $this->assertSame(16, $standing[0]['points']);
    }

    public function test_the_standing_hands_out_no_id_for_an_anonymized_fencer(): void
    {
        $this->addResult($anonymous = $this->fencer('Erika', 'Mustermann'), 1);
        AnonymizeFencerAction::anonymize($anonymous);

        $key = \App\Models\ApiKey::create(['name' => 'Test', 'key' => str_repeat('M', 64)])->key;

        // An id here would be a link straight to a 404 - and following it is exactly how somebody
        // would try to put the name back.
        $this->withToken($key)
            ->getJson("/api/seasons/{$this->season->public_id}/standing")
            ->assertOk()
            ->assertJsonPath('standing.0.fencer.id', null)
            ->assertJsonPath('standing.0.fencer.name', Fencer::ANONYMOUS_NAME)
            ->assertJsonPath('standing.0.fencer.group', null);
    }

    public function test_the_standing_names_no_tournaments_for_an_anonymized_fencer(): void
    {
        $this->addResult($this->fencer('Max', 'Musterlein'), 1);
        $this->addResult($anonymous = $this->fencer('Erika', 'Mustermann'), 2);
        AnonymizeFencerAction::anonymize($anonymous);

        $key = \App\Models\ApiKey::create(['name' => 'Test', 'key' => str_repeat('N', 64)])->key;

        $response = $this->withToken($key)
            ->getJson("/api/seasons/{$this->season->public_id}/standing")
            ->assertOk();

        // Which tournaments somebody attended is an itinerary, and an itinerary identifies a
        // person. The points survive, because the rank is made of them.
        $response->assertJsonPath('standing.1.points', 6);
        $response->assertJsonPath('standing.1.results', []);

        // Everybody else keeps their breakdown, or the endpoint would have stopped being useful.
        $response->assertJsonCount(1, 'standing.0.results');
        $response->assertJsonPath('standing.0.results.0.tournament_id', $this->tournament->public_id);
    }

    public function test_a_merely_deactivated_fencer_stays_in_the_standing(): void
    {
        $this->addResult($retired = $this->fencer('Max', 'Musterlein'), 1);
        $retired->update(['is_active' => false]);

        // The obvious wrong fix is to filter on is_active. Someone who stopped competing must
        // not vanish from the standings of past seasons.
        $names = array_map(fn ($entry) => $entry['fencer']['display_name'], $this->standing());

        $this->assertSame(['Max Musterlein'], $names);
    }

    public function test_the_tournament_result_keeps_the_placement_but_loses_the_identity(): void
    {
        $result = $this->addResult($anonymous = $this->fencer('Erika', 'Mustermann'), 2);
        AnonymizeFencerAction::anonymize($anonymous);

        $payload = (new ResultResource($result->fresh()->load('fencer.group')))->toArray(Request::create('/'));

        // A string, and deliberately so: the field carries a rank here but may carry "last-16" or
        // "pools" for the same tournament's other rows, because that is what those results were.
        $this->assertSame('2', $payload['placement']);
        $this->assertSame('2.', $payload['placement_name']);
        $this->assertNull($payload['fencer']['id']);
        $this->assertNull($payload['fencer']['group']);
        $this->assertSame(Fencer::ANONYMOUS_NAME, $payload['fencer']['name']);
    }

    public function test_the_placeholder_wins_over_a_name_left_in_the_record(): void
    {
        $result = $this->addResult($fencer = $this->fencer('Erika', 'Mustermann'), 1);

        // Anonymised by hand and only half finished: the flag is set, the name was forgotten.
        // The API must still not hand out the name.
        $fencer->update(['anonymized_at' => now()]);

        $payload = (new ResultResource($result->fresh()->load('fencer.group')))->toArray(Request::create('/'));

        $this->assertSame(Fencer::ANONYMOUS_NAME, $payload['fencer']['name']);
        $this->assertNull($payload['fencer']['id']);
    }

    public function test_anonymizing_clears_every_personal_attribute(): void
    {
        $fencer = Fencer::create([
            'first_name'    => 'Erika',
            'last_name'     => 'Mustermann',
            'birth_name'    => 'Musterfrau',
            'title'         => 'doctor',
            'nationality'   => 'DE',
            'date_of_birth' => '1990-01-01',
            'gender'        => 'female',
            'is_active'     => true,
            'group_id'      => Group::create(['name' => 'Schwabenfedern', 'is_active' => true])->id,
        ]);
        $result = Result::create([
            'tournament_id'     => $this->tournament->id,
            'fencer_id'         => $fencer->id,
            'group_id'          => $fencer->group_id,
            'federation_id'     => $this->ddhf->id,
            'placement'         => 1,
            'fencer_group_name' => 'Schwabenfedern',
        ]);

        AnonymizeFencerAction::anonymize($fencer, 'Löschwunsch');
        $fencer->refresh();

        $this->assertNull($fencer->title);
        $this->assertNull($fencer->birth_name);
        $this->assertNull($fencer->nationality);
        $this->assertNull($fencer->date_of_birth);
        $this->assertNull($fencer->gender);
        $this->assertNull($fencer->group_id);
        $this->assertFalse((bool) $fencer->is_active);
        $this->assertTrue($fencer->isAnonymized());

        // The name is gone from the record itself. What a reader sees is put there by the code,
        // which is why the columns are empty and the display name is not.
        $this->assertNull($fencer->first_name);
        $this->assertNull($fencer->last_name);
        $this->assertSame(Fencer::ANONYMOUS_NAME, $fencer->display_name);
        $this->assertSame(Fencer::ANONYMOUS_NAME, $fencer->display_name_with_group);

        // The club recorded with a result is the last identifying trait once the rest is gone -
        // both the resolved one and the spelling the source file used.
        $result = $result->fresh();
        $this->assertNull($result->group_id);
        $this->assertNull($result->fencer_group_name);

        // Placement and federation stay, or the entry could not keep its place in the standing.
        $this->assertSame('1', (string) $result->placement);
        $this->assertSame($this->ddhf->id, $result->federation_id);
    }

    public function test_the_api_stops_resolving_an_anonymized_fencer(): void
    {
        $anonymous = $this->fencer('Erika', 'Mustermann');
        $retired = $this->fencer('Max', 'Musterlein');
        $retired->update(['is_active' => false]);
        AnonymizeFencerAction::anonymize($anonymous);

        $key = \App\Models\ApiKey::create(['name' => 'Test', 'key' => str_repeat('K', 64)])->key;
        $get = fn (string $id) => $this->withToken($key)->getJson("/api/fencers/{$id}");

        $get($anonymous->public_id)->assertNotFound();

        // A fencer who is merely deactivated stays resolvable, because the standings of past
        // seasons still refer to them.
        $get($retired->public_id)->assertOk()->assertJsonPath('data.id', $retired->public_id);
    }

    public function test_the_fencer_list_never_contains_anonymized_records(): void
    {
        $anonymous = $this->fencer('Erika', 'Mustermann');
        AnonymizeFencerAction::anonymize($anonymous);
        // Force the contradictory state the whereNull guard exists for.
        $anonymous->update(['is_active' => true]);

        $key = \App\Models\ApiKey::create(['name' => 'Test', 'key' => str_repeat('L', 64)])->key;

        $this->withToken($key)
            ->getJson('/api/fencers')
            ->assertOk()
            ->assertJsonMissing(['id' => $anonymous->public_id]);
    }
}
