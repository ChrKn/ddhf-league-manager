<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Federations\Pages\EditFederation;
use App\Filament\Resources\Federations\Pages\ListFederations;
use App\Filament\Resources\Groups\Pages\EditGroup;
use App\Filament\Resources\Rulesets\Pages\EditRuleset;
use App\Filament\Resources\Seasons\Pages\EditSeason;
use App\Models\Discipline;
use App\Models\Division;
use App\Models\Event;
use App\Models\Federation;
use App\Models\Fencer;
use App\Models\Group;
use App\Models\Result;
use App\Models\Ruleset;
use App\Models\ScoringMatrix;
use App\Models\Season;
use App\Models\Standing;
use App\Models\Tournament;
use App\Models\User;
use App\Standings\ScoringMode;
use App\Standings\SeasonRanking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The panel refuses to delete what something else depends on.
 *
 * Two different failures used to hide behind one button. The database refuses the load-bearing
 * deletions itself, but as a raw SQL error nobody can act on. And the references that are SET NULL
 * went through: deleting a federation emptied `results.federation_id` for every result ever fenced
 * for it, and a standing scores nobody whose result does not name our own federation - so one
 * click emptied every table, silently.
 *
 * The first test is the one that matters, because it asks after the consequence rather than the
 * button: the standing is still there afterwards.
 */
class GuardedDeleteTest extends TestCase
{
    use RefreshDatabase;

    private Federation $ddhf;

    private Group $club;

    private Season $season;

    private Tournament $tournament;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsMaintainer();

        $this->ddhf = Federation::create([
            'name'         => 'Deutscher Dachverband für historisches Fechten e. V.',
            'abbreviation' => Federation::OWN,
            'is_active'    => true,
        ]);

        $this->club = Group::create(['name' => 'Schildwache Potsdam', 'is_active' => true]);
        $this->club->federations()->attach($this->ddhf);

        $this->season = Season::create([
            'year'              => 2026,
            'standing_id'       => Standing::create([
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
            'scoring_mode' => ScoringMode::Standard,
        ]);

        $this->tournament = Tournament::create([
            'season_id'         => $this->season->id,
            'event_id'          => Event::create(['name' => 'Musterturnier', 'start_date' => '2026-05-09'])->id,
            'participant_count' => 20,
        ]);

        Result::create([
            'tournament_id' => $this->tournament->id,
            'fencer_id'     => Fencer::create([
                'first_name' => 'Test',
                'last_name'  => 'Fechterin',
                'is_active'  => true,
                'group_id'   => $this->club->id,
            ])->id,
            'group_id'      => $this->club->id,
            'federation_id' => $this->ddhf->id,
            'placement'     => '1',
        ]);
    }

    private function ranked(): int
    {
        return count(SeasonRanking::for($this->season->fresh()->load(SeasonRanking::relations()))['rows']);
    }

    public function test_deleting_the_federation_would_empty_every_standing_and_is_refused(): void
    {
        $this->assertSame(1, $this->ranked());

        Livewire::test(EditFederation::class, ['record' => $this->ddhf->getKey()])
            ->callAction('delete')
            ->assertNotified();

        $this->assertNotNull($this->ddhf->fresh(), 'Der Verband ist weg.');

        // The point of the whole exercise. `results.federation_id` is SET NULL, so the delete
        // would have succeeded and taken every ranking with it without a single error.
        $this->assertSame(1, $this->ranked(), 'Die Rangliste ist leer — das Loch war offen.');
    }

    public function test_the_message_names_what_is_in_the_way(): void
    {
        Livewire::test(EditFederation::class, ['record' => $this->ddhf->getKey()])
            ->callAction('delete');

        // "Wird nicht gelöscht" on its own leaves somebody guessing. The count and the noun are
        // what turns a refusal into something actionable.
        $shown = json_encode($this->notifications(), JSON_UNESCAPED_UNICODE);

        $this->assertStringContainsString('1 Ergebnisse', $shown);
        $this->assertStringContainsString('1 Vereine', $shown);
    }

    public function test_a_club_with_results_is_refused(): void
    {
        Livewire::test(EditGroup::class, ['record' => $this->club->getKey()])
            ->callAction('delete')
            ->assertNotified();

        $this->assertNotNull($this->club->fresh());
        $this->assertSame($this->club->id, Result::sole()->group_id);
    }

    public function test_a_ruleset_a_tournament_uses_is_refused(): void
    {
        $ruleset = Ruleset::create(['name' => 'Beispielregelwerk']);
        $this->tournament->update(['ruleset_id' => $ruleset->id]);

        Livewire::test(EditRuleset::class, ['record' => $ruleset->getKey()])
            ->callAction('delete')
            ->assertNotified();

        // SET NULL again: the tournament would have kept running with no ruleset, and a
        // ruleset-mode standing would quietly stop counting it.
        $this->assertNotNull($ruleset->fresh());
        $this->assertSame($ruleset->id, $this->tournament->fresh()->ruleset_id);
    }

    public function test_a_season_with_tournaments_is_refused_before_the_database_complains(): void
    {
        // This one the database would refuse anyway - it is RESTRICT - but with an SQL error
        // rather than a sentence.
        Livewire::test(EditSeason::class, ['record' => $this->season->getKey()])
            ->callAction('delete')
            ->assertNotified();

        $this->assertNotNull($this->season->fresh());
    }

    public function test_a_record_nothing_points_at_still_goes(): void
    {
        $unused = Ruleset::create(['name' => 'Versehentlich angelegt']);

        Livewire::test(EditRuleset::class, ['record' => $unused->getKey()])
            ->callAction('delete');

        // Otherwise the panel fills up with mistakes nobody can clear away, and the guard becomes
        // a reason to edit the database by hand.
        $this->assertNull($unused->fresh());
    }

    public function test_the_tables_of_record_offer_no_bulk_delete(): void
    {
        // A bulk delete that half succeeds is worse than none: it is neither a correction nor a
        // clean failure, and nobody can tell afterwards which half went.
        $bulk = Livewire::test(ListFederations::class)
            ->instance()
            ->getTable()
            ->getFlatBulkActions();

        $this->assertSame([], array_keys($bulk));
    }

    /** @return list<array<string, mixed>> */
    private function notifications(): array
    {
        $component = new \Filament\Notifications\Livewire\Notifications();
        $component->mount();

        return $component->notifications
            ->map(fn ($notification): array => $notification->toArray())
            ->values()
            ->all();
    }
}
