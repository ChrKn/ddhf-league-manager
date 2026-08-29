<?php

namespace Tests\Feature\Fencers;

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
use Tests\TestCase;

/**
 * One person entered twice, folded back into one record.
 *
 * The names here are invented, as everywhere in this repository. That is not only the rule but the
 * point of the command's interface: two records worth merging are two records whose names differ,
 * so the name is the wrong handle and the public id is the right one.
 */
class MergeFencersTest extends TestCase
{
    use RefreshDatabase;

    private Federation $ddhf;

    private Group $club;

    private Season $season;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ddhf = Federation::create([
            'name'         => 'Deutscher Dachverband für historisches Fechten e. V.',
            'abbreviation' => Federation::OWN,
            'is_active'    => true,
        ]);

        $this->club = Group::create(['name' => 'Schildwache Potsdam', 'is_active' => true]);
        $this->club->federations()->attach($this->ddhf);

        $this->season = $this->season(2025);
    }

    private function season(int $year): Season
    {
        return Season::create([
            'year'              => $year,
            'standing_id'       => Standing::create([
                'discipline_id' => Discipline::firstOrCreate(['name' => 'Langes Schwert'])->id,
                'division_id'   => Division::create(['name' => "offen {$year}"])->id,
            ])->id,
            'scoring_matrix_id' => ScoringMatrix::firstOrCreate(['name' => 'Test'], [
                'matrix' => json_encode([[
                    'participants' => ['min' => 0],
                    'points'       => [['place' => ['min' => 1, 'points' => 10]]],
                ]]),
            ])->id,
            'scoring_mode' => ScoringMode::Standard,
        ]);
    }

    private function fencer(string $first, string $last, array $attributes = []): Fencer
    {
        return Fencer::create(array_merge([
            'first_name' => $first,
            'last_name'  => $last,
            'is_active'  => true,
            'group_id'   => $this->club->id,
        ], $attributes));
    }

    private function placed(Fencer $fencer, Season $season, int $place): Result
    {
        $tournament = Tournament::create([
            'season_id'         => $season->id,
            'event_id'          => Event::create([
                'name'       => "Turnier {$season->year} " . uniqid(),
                'start_date' => "{$season->year}-05-09",
            ])->id,
            'participant_count' => 40,
        ]);

        return Result::create([
            'tournament_id' => $tournament->id,
            'fencer_id'     => $fencer->id,
            'group_id'      => $this->club->id,
            'federation_id' => $this->ddhf->id,
            'placement'     => (string) $place,
        ]);
    }

    private function merge(Fencer $keep, Fencer $gone, array $options = []): \Illuminate\Testing\PendingCommand
    {
        return $this->artisan('fencers:merge', array_merge([
            'keep'  => $keep->public_id,
            'merge' => [$gone->public_id],
        ], $options));
    }

    public function test_the_results_of_both_end_up_on_the_one_that_stays(): void
    {
        $keep = $this->fencer('Vorname', 'Nachnahme');
        $gone = $this->fencer('Vornahme', 'Nachnahme');

        $this->placed($keep, $this->season, 41);
        $this->placed($gone, $this->season(2026), 20);

        $this->merge($keep, $gone)->assertSuccessful();

        $this->assertSame(2, $keep->results()->count());
        $this->assertNull(Fencer::where('public_id', $gone->public_id)->first());
    }

    public function test_nothing_is_lost_that_was_not_meant_to_be(): void
    {
        $keep = $this->fencer('Vorname', 'Nachnahme');
        $gone = $this->fencer('Vornahme', 'Nachnahme');
        $other = $this->fencer('Unbeteiligt', 'Fechterin');

        $this->placed($keep, $this->season, 41);
        $this->placed($gone, $this->season(2026), 20);
        $this->placed($other, $this->season, 1);

        $this->merge($keep, $gone)->assertSuccessful();

        // Three results before, three after - they moved, none disappeared with the record.
        $this->assertSame(3, Result::count());
        $this->assertSame(1, $other->results()->count());
    }

    public function test_a_dry_run_says_what_would_happen_and_does_nothing(): void
    {
        $keep = $this->fencer('Vorname', 'Nachnahme');
        $gone = $this->fencer('Vornahme', 'Nachnahme');
        $this->placed($gone, $this->season, 20);

        $this->merge($keep, $gone, ['--dry-run' => true])->assertSuccessful();

        $this->assertSame(0, $keep->results()->count());
        $this->assertNotNull(Fencer::where('public_id', $gone->public_id)->first());
    }

    public function test_two_placements_in_one_tournament_stop_the_merge(): void
    {
        $keep = $this->fencer('Vorname', 'Nachnahme');
        $gone = $this->fencer('Vornahme', 'Nachnahme');

        $tournament = Tournament::create([
            'season_id'         => $this->season->id,
            'event_id'          => Event::create(['name' => 'Dasselbe Turnier', 'start_date' => '2025-05-09'])->id,
            'participant_count' => 40,
        ]);

        foreach ([$keep, $gone] as $index => $fencer) {
            Result::create([
                'tournament_id' => $tournament->id,
                'fencer_id'     => $fencer->id,
                'group_id'      => $this->club->id,
                'federation_id' => $this->ddhf->id,
                'placement'     => (string) (10 + $index),
            ]);
        }

        // Merging would give one person two placements in one tournament. Either the two are not
        // the same person or one of the results is wrong, and neither is this command's to decide.
        $this->merge($keep, $gone)->assertFailed();

        $this->assertNotNull(Fencer::where('public_id', $gone->public_id)->first());
        $this->assertSame(1, $keep->results()->count());
    }

    public function test_an_empty_field_is_filled_from_the_record_that_had_it(): void
    {
        $keep = $this->fencer('Vorname', 'Nachnahme');
        $gone = $this->fencer('Vornahme', 'Nachnahme', ['nationality' => 'DE']);

        $this->merge($keep, $gone)->assertSuccessful();

        $this->assertSame('DE', $keep->fresh()->nationality);
    }

    public function test_a_field_the_survivor_already_has_is_left_alone(): void
    {
        $keep = $this->fencer('Vorname', 'Nachnahme', ['nationality' => 'DE']);
        $gone = $this->fencer('Vornahme', 'Nachnahme', ['nationality' => 'AT']);

        $this->merge($keep, $gone)->assertSuccessful();

        $this->assertSame('DE', $keep->fresh()->nationality);
    }

    public function test_records_that_disagree_leave_the_field_empty(): void
    {
        $keep = $this->fencer('Vorname', 'Nachnahme');
        $one = $this->fencer('Vornahme', 'Nachnahme', ['nationality' => 'DE']);
        $two = $this->fencer('Vorname', 'Nachname', ['nationality' => 'AT']);

        $this->artisan('fencers:merge', [
            'keep'  => $keep->public_id,
            'merge' => [$one->public_id, $two->public_id],
        ])->assertSuccessful();

        // Knowing that three records are one person is not knowing which nationality is right.
        $this->assertNull($keep->fresh()->nationality);
    }

    public function test_the_ranking_choice_moves_across_without_doubling_up(): void
    {
        $keep = $this->fencer('Vorname', 'Nachnahme');
        $gone = $this->fencer('Vornahme', 'Nachnahme');

        $other = $this->season(2027);
        $keep->seasons()->attach($this->season);
        $gone->seasons()->attach([$this->season->id, $other->id]);

        $this->merge($keep, $gone)->assertSuccessful();

        $this->assertSame(
            [$this->season->id, $other->id],
            $keep->fresh()->seasons()->pluck('seasons.id')->sort()->values()->all(),
        );
    }

    public function test_an_anonymised_record_is_refused(): void
    {
        $keep = $this->fencer('Vorname', 'Nachnahme');
        $gone = $this->fencer('Vornahme', 'Nachnahme');
        $gone->update(['anonymized_at' => now(), 'first_name' => null, 'last_name' => null]);

        // What identified them is gone, so there is nothing left to check "same person" against.
        $this->merge($keep, $gone)->assertFailed();

        $this->assertNotNull(Fencer::where('public_id', $gone->public_id)->first());
    }

    public function test_an_unknown_id_is_refused_before_anything_moves(): void
    {
        $keep = $this->fencer('Vorname', 'Nachnahme');

        $this->artisan('fencers:merge', ['keep' => $keep->public_id, 'merge' => ['GIBTSNICHT']])
            ->assertFailed();

        $this->assertSame(1, Fencer::count());
    }

    public function test_merging_a_record_into_itself_is_refused(): void
    {
        $keep = $this->fencer('Vorname', 'Nachnahme');

        $this->merge($keep, $keep)->assertFailed();

        $this->assertNotNull($keep->fresh());
    }
}
