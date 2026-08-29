<?php

namespace Tests\Feature\Browse;

use App\Filament\Resources\Fencers\Actions\AnonymizeFencerAction;
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
 * The data browser exists to be read by a person, which is the whole of its specification: every
 * foreign key has to arrive as the name it points at, and the numbers it shows have to be the
 * same numbers the API and the standings calculator produce. A page that quietly disagreed with
 * the calculator would be worse than no page at all - it would be a second opinion nobody asked
 * for while looking like a check.
 */
class DataBrowserTest extends TestCase
{
    use RefreshDatabase;

    private Federation $ddhf;

    private Group $club;

    private Season $season;

    private Tournament $tournament;

    protected function setUp(): void
    {
        parent::setUp();

        // The data browser is part of the admin panel now.
        $this->actingAsMaintainer();

        $this->ddhf = Federation::create([
            'name'         => 'Deutscher Dachverband für historisches Fechten e. V.',
            'abbreviation' => Federation::OWN,
            'country'      => 'DE',
            'is_active'    => true,
        ]);

        $this->club = Group::create([
            'name'          => 'Ochs Historische Kampfkünste',
            'country'       => 'DE',
            'is_active'     => true,
        ]);

        $this->club->federations()->attach($this->ddhf);

        $this->season = Season::create([
            'year'              => 2024,
            'standing_id'       => Standing::create([
                'discipline_id' => Discipline::create(['name' => 'Langes Schwert'])->id,
                'division_id'   => Division::create(['name' => 'offen'])->id,
            ])->id,
            'scoring_matrix_id' => $this->matrix()->id,
            'scoring_mode'      => ScoringMode::Standard,
        ]);

        $this->tournament = Tournament::create([
            'name'              => 'Hanseschlag 2024 Langes Schwert offen',
            'season_id'         => $this->season->id,
            'event_id'          => Event::create([
                'name'       => 'Hanseschlag',
                'location'   => 'Hamburg',
                'start_date' => '2024-05-04',
            ])->id,
            'participant_count' => 3,
            'format'            => 'Turnierbaum',
        ]);
    }

    private function matrix(): ScoringMatrix
    {
        return ScoringMatrix::create([
            'name'   => 'Punkteschlüssel Test',
            'matrix' => json_encode([[
                'participants' => ['min' => 0],
                'points'       => [
                    ['place' => ['min' => 1, 'points' => 10]],
                    ['place' => ['min' => 2, 'points' => 6]],
                    ['place' => ['min' => 3, 'points' => 3]],
                ],
            ]]),
        ]);
    }

    private function fencer(string $first, string $last, ?Group $group = null): Fencer
    {
        return Fencer::create([
            'first_name' => $first,
            'last_name'  => $last,
            'is_active'  => true,
            'group_id'   => ($group ?? $this->club)->id,
        ]);
    }

    private function addResult(Fencer $fencer, int $placement, ?Group $group = null, ?Tournament $tournament = null): Result
    {
        $group ??= $this->club;

        return Result::create([
            'tournament_id'     => ($tournament ?? $this->tournament)->id,
            'fencer_id'         => $fencer->id,
            'group_id'          => $group->id,
            'federation_id'     => $group->scoringFederation()?->id,
            'placement'         => $placement,
            'fencer_group_name' => $group->name,
        ]);
    }

    public function test_every_page_answers(): void
    {
        $this->addResult($this->fencer('Peter', 'Beispiel'), 1);

        foreach ([
            \App\Filament\Pages\Browse\Overview::getUrl(),
            \App\Filament\Pages\Browse\Federations::getUrl(),
            \App\Filament\Pages\Browse\Groups::getUrl(),
            \App\Filament\Pages\Browse\Fencers::getUrl(),
            \App\Filament\Pages\Browse\Events::getUrl(),
            \App\Filament\Pages\Browse\Tournaments::getUrl(),
            \App\Filament\Pages\Browse\Seasons::getUrl(),
            \App\Filament\Pages\Browse\Standings::getUrl(),
            \App\Filament\Pages\Browse\Tournament::getUrl(['public_id' => $this->tournament->public_id]),
            \App\Filament\Pages\Browse\Ranking::getUrl(['public_id' => $this->season->public_id]),
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_a_club_names_its_federation_instead_of_pointing_at_a_row(): void
    {
        // The one thing this whole view is for: an id in a cell tells a reader nothing.
        // Read back from the model rather than repeated as a literal: NameNormalization rewrites
        // "e. V." with a narrow no-break space, and the page shows the stored name, not the typed one.
        $this->get(\App\Filament\Pages\Browse\Groups::getUrl())
            ->assertOk()
            ->assertSee('Ochs Historische Kampfkünste')
            ->assertSee($this->ddhf->name)
            ->assertSee('Deutschland')
            ->assertDontSee('federation_id');
    }

    public function test_a_fencer_names_the_club_and_the_federation_behind_it(): void
    {
        $this->fencer('Peter', 'Beispiel');

        $this->get(\App\Filament\Pages\Browse\Fencers::getUrl())
            ->assertOk()
            ->assertSee('Peter Beispiel')
            ->assertSee('Ochs Historische Kampfkünste')
            ->assertSee($this->ddhf->name);
    }

    public function test_a_tournament_names_its_event_and_season_and_computes_the_points(): void
    {
        $this->addResult($this->fencer('Peter', 'Beispiel'), 1);
        $this->addResult($this->fencer('Yuri', 'Mustermann'), 3);

        $response = $this->get(\App\Filament\Pages\Browse\Tournament::getUrl(['public_id' => $this->tournament->public_id]))->assertOk();

        $response->assertSee('Hanseschlag')
            ->assertSee('Hamburg')
            ->assertSee('Langes Schwert')
            ->assertSee('Turnierbaum')
            ->assertSee('Punkteschlüssel Test');

        // Points are never stored, so seeing them at all proves they were derived here.
        $response->assertSeeInOrder(['Peter Beispiel', '10'])
            ->assertSeeInOrder(['Yuri Mustermann', '3']);
    }

    public function test_the_ranking_lists_the_same_people_and_points_as_the_calculator(): void
    {
        $this->addResult($this->fencer('Peter', 'Beispiel'), 1);
        $this->addResult($this->fencer('Yuri', 'Mustermann'), 2);
        $this->addResult($this->fencer('Anna', 'Testfall'), 3);

        $this->get(\App\Filament\Pages\Browse\Ranking::getUrl(['public_id' => $this->season->public_id]))
            ->assertOk()
            ->assertSeeInOrder(['Peter Beispiel', 'Yuri Mustermann', 'Anna Testfall']);
    }

    public function test_a_fencer_of_no_member_club_is_absent_from_the_ranking(): void
    {
        // Same rule as the API: membership is read off the result. The browser must not show a
        // friendlier ranking than the one that counts.
        $abroad = Group::create(['name' => 'Sprezzatura', 'country' => 'AT', 'is_active' => true]);

        $this->addResult($this->fencer('Peter', 'Beispiel'), 1);
        $this->addResult($this->fencer('Anna', 'Testfrau', $abroad), 2, $abroad);

        $this->get(\App\Filament\Pages\Browse\Ranking::getUrl(['public_id' => $this->season->public_id]))
            ->assertOk()
            ->assertSee('Peter Beispiel')
            ->assertDontSee('Anna Testfrau');
    }

    public function test_an_anonymised_fencer_keeps_their_row_in_the_ranking_without_a_name_or_a_club(): void
    {
        // Her own club, so that seeing its name on the page can only come from her row.
        $herClub = Group::create([
            'name'          => 'Fechtschule Musterstadt',
            'country'       => 'DE',
            'is_active'     => true,
        ]);

        $herClub->federations()->attach($this->ddhf);

        $anonymous = $this->fencer('Erika', 'Mustermann', $herClub);

        // A second tournament that only the anonymised fencer attended, so that its name can only
        // reach the page through her breakdown and nowhere else.
        $second = Tournament::create([
            'name'              => 'Musterturnier 2024',
            'season_id'         => $this->season->id,
            'event_id'          => Event::create([
                'name'       => 'Musterstädter Fechtschul',
                'start_date' => '2024-08-17',
            ])->id,
            'participant_count' => 3,
        ]);

        $this->addResult($this->fencer('Peter', 'Beispiel'), 1);
        $this->addResult($anonymous, 2, $herClub);
        $this->addResult($anonymous, 1, $herClub, $second);
        AnonymizeFencerAction::anonymize($anonymous, 'Auf Wunsch');

        // The row stays where it was fenced, so nobody behind it moves up - but nothing on it
        // points at a person any more. The club is the last identifying trait, and the list of
        // tournaments is the one after that: an itinerary held against a start list names
        // somebody as surely as a name would.
        $this->get(\App\Filament\Pages\Browse\Ranking::getUrl(['public_id' => $this->season->public_id]))
            ->assertOk()
            ->assertSee('Peter Beispiel')
            ->assertSee(Fencer::ANONYMOUS_NAME)
            ->assertDontSee('Erika Mustermann')
            ->assertDontSee('Fechtschule Musterstadt')
            ->assertDontSee('Musterturnier 2024');

        // A placement stays a fact about the tournament even when the person is gone, so the
        // tournament's own page still shows the whole field.
        $this->get(\App\Filament\Pages\Browse\Tournament::getUrl(['public_id' => $second->public_id]))
            ->assertOk()
            ->assertSee(Fencer::ANONYMOUS_NAME)
            ->assertDontSee('Erika Mustermann');
    }

    public function test_filtering_the_clubs_by_federation_narrows_the_table(): void
    {
        Group::create(['name' => 'Sprezzatura', 'country' => 'AT', 'is_active' => true]);

        $this->get(\App\Filament\Pages\Browse\Groups::getUrl(['federation' => $this->ddhf->public_id]))
            ->assertOk()
            ->assertSee('Ochs Historische Kampfkünste')
            ->assertDontSee('Sprezzatura');
    }

    public function test_the_clubs_without_a_federation_can_be_singled_out(): void
    {
        // Fourteen German clubs carry results without a federation, and that open question has
        // to stay one click away rather than needing a query.
        Group::create(['name' => 'Ort Coburg', 'country' => 'DE', 'is_active' => true]);

        $this->get(\App\Filament\Pages\Browse\Groups::getUrl(['federation' => 'none']))
            ->assertOk()
            ->assertSee('Ort Coburg')
            ->assertDontSee('Ochs Historische Kampfkünste');
    }

    public function test_a_result_that_does_not_count_towards_the_total_is_marked_as_such(): void
    {
        // Best-three drops everything past the third tournament. Which ones were dropped is the
        // first thing anyone checking a best-three season wants to know, so it cannot be implied
        // by arithmetic alone.
        $this->season->update(['scoring_mode' => ScoringMode::BestThree]);
        $fencer = $this->fencer('Peter', 'Beispiel');

        $this->addResult($fencer, 1);

        foreach ([2, 3, 4] as $index) {
            $tournament = Tournament::create([
                'name'              => "Turnier $index",
                'season_id'         => $this->season->id,
                'event_id'          => Event::create([
                    'name'       => "Veranstaltung $index",
                    'start_date' => '2024-06-0' . $index,
                ])->id,
                'participant_count' => 3,
            ]);

            $this->addResult($fencer, 3, null, $tournament);
        }

        $this->get(\App\Filament\Pages\Browse\Ranking::getUrl(['public_id' => $this->season->public_id]))
            ->assertOk()
            // 10 + 3 + 3, and the fourth outing left out.
            ->assertSee('zählt nicht');
    }

    public function test_a_zone_season_names_the_zone_a_fencer_was_ranked_through(): void
    {
        $this->season->update(['scoring_mode' => ScoringMode::Zone]);
        $this->tournament->update(['region' => 'north']);

        $this->addResult($this->fencer('Peter', 'Beispiel'), 1);

        $this->get(\App\Filament\Pages\Browse\Ranking::getUrl(['public_id' => $this->season->public_id]))
            ->assertOk()
            ->assertSee('Gewertet über')
            ->assertSee('Norden');
    }

    public function test_an_unknown_tournament_is_a_404_rather_than_a_blank_table(): void
    {
        $this->get(\App\Filament\Pages\Browse\Tournament::getUrl(['public_id' => 'GIBTSNICHT']))->assertNotFound();
        $this->get(\App\Filament\Pages\Browse\Ranking::getUrl(['public_id' => 'GIBTSNICHT']))->assertNotFound();
    }

    public function test_a_season_without_a_usable_scoring_matrix_says_so(): void
    {
        // A broken matrix must not read as "nobody scored anything".
        $this->addResult($this->fencer('Peter', 'Beispiel'), 1);
        ScoringMatrix::find($this->season->scoring_matrix_id)->update(['matrix' => 'kein JSON']);

        $this->get(\App\Filament\Pages\Browse\Ranking::getUrl(['public_id' => $this->season->public_id]))
            ->assertOk()
            ->assertSee('lässt sich nicht berechnen');
    }

    public function test_the_overview_counts_what_is_on_record(): void
    {
        $this->addResult($this->fencer('Peter', 'Beispiel'), 1);

        $this->get(\App\Filament\Pages\Browse\Overview::getUrl())
            ->assertOk()
            ->assertSee('Verbände')
            ->assertSee('Vereine ohne Verband')
            ->assertSee('Fechter ohne Verein');
    }
}
