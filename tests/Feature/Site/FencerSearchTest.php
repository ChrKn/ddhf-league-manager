<?php

namespace Tests\Feature\Site;

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
 * Finding a person without knowing which standing to look in.
 *
 * The rule the whole thing hangs on: **the search may only surface what a standing already shows.**
 * The raw results say more than the published tables do - somebody who fenced for a club that is
 * not a member scores nothing and appears in no table - so searching the results directly would
 * hand back exactly the people the standings leave out. Three of the tests below are that one
 * sentence, asked from three directions.
 */
class FencerSearchTest extends TestCase
{
    use RefreshDatabase;

    private Federation $ddhf;

    private Group $member;

    private ScoringMatrix $matrix;

    private Discipline $discipline;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ddhf = Federation::create([
            'name'         => 'Deutscher Dachverband für historisches Fechten e. V.',
            'abbreviation' => Federation::OWN,
            'is_active'    => true,
        ]);

        $this->member = Group::create(['name' => 'Schildwache Potsdam', 'is_active' => true]);
        $this->member->federations()->attach($this->ddhf);

        $this->matrix = ScoringMatrix::create([
            'name'   => 'Test',
            'matrix' => json_encode([[
                'participants' => ['min' => 0],
                'points'       => [['place' => ['min' => 1, 'points' => 10]], ['place' => ['min' => 2, 'points' => 6]]],
            ]]),
        ]);

        $this->discipline = Discipline::create(['name' => 'Langes Schwert']);
    }

    private function season(int $year, string $division = 'offen'): Season
    {
        return Season::create([
            'year'              => $year,
            'standing_id'       => Standing::create([
                'discipline_id' => $this->discipline->id,
                'division_id'   => Division::create(['name' => $division . ' ' . $year])->id,
            ])->id,
            'scoring_matrix_id' => $this->matrix->id,
            'scoring_mode'      => ScoringMode::Standard,
        ]);
    }

    private function fencer(string $first, string $last, ?Group $club = null): Fencer
    {
        return Fencer::create([
            'first_name' => $first,
            'last_name'  => $last,
            'is_active'  => true,
            'group_id'   => ($club ?? $this->member)->id,
        ]);
    }

    private function placed(Fencer $fencer, Season $season, int $place, ?Group $club = null): void
    {
        $club ??= $this->member;

        $tournament = Tournament::create([
            'season_id'         => $season->id,
            'event_id'          => Event::create([
                'name'       => 'Turnier ' . $season->year . ' ' . uniqid(),
                'start_date' => $season->year . '-05-09',
            ])->id,
            'participant_count' => 20,
        ]);

        Result::create([
            'tournament_id' => $tournament->id,
            'fencer_id'     => $fencer->id,
            'group_id'      => $club->id,
            'federation_id' => $club->scoringFederation()?->id,
            'placement'     => (string) $place,
        ]);
    }

    public function test_a_name_finds_the_standings_it_appears_in(): void
    {
        $fencer = $this->fencer('Vorname', 'Nachnahme');
        $this->placed($fencer, $this->season(2025), 1);
        $this->placed($fencer, $this->season(2026), 2);

        // The point of the page: two tables, one search, without knowing which to open.
        $this->get('/suche?q=Nachnahme')
            ->assertOk()
            ->assertSee('Vorname Nachnahme')
            ->assertSee('Langes Schwert offen 2025 2025')
            ->assertSee('Langes Schwert offen 2026 2026');
    }

    public function test_the_full_name_finds_them_too(): void
    {
        $this->placed($this->fencer('Vorname', 'Nachnahme'), $this->season(2026), 1);

        $this->get('/suche?q=' . urlencode('Vorname Nachnahme'))
            ->assertOk()
            ->assertSee('Vorname Nachnahme');
    }

    public function test_somebody_no_standing_lists_is_not_findable(): void
    {
        $outsider = Group::create(['name' => 'Kein Mitglied', 'is_active' => true]);
        $fencer = $this->fencer('Vorname', 'Fremdling', $outsider);

        $this->placed($fencer, $this->season(2026), 1, $outsider);

        // They have a result and a name, and they are in no table, because the club is not a
        // member. Searching the results rather than the standings would have found them.
        $this->get('/suche?q=Fremdling')
            ->assertOk()
            ->assertDontSee('Vorname Fremdling')
            ->assertSee('steht niemand in den Ranglisten');
    }

    public function test_an_anonymised_fencer_is_not_findable(): void
    {
        $fencer = $this->fencer('Vorname', 'Nachnahme');
        $this->placed($fencer, $this->season(2026), 1);

        AnonymizeFencerAction::anonymize($fencer);

        // Their name was deleted, so there is nothing to match - and the placeholder must not
        // behave like one either. Asked against the full name rather than the fragment: the
        // fragment comes back on the page by itself, in the search box and in the message.
        $this->get('/suche?q=Nachnahme')
            ->assertOk()
            ->assertDontSee('Vorname Nachnahme')
            ->assertSee('steht niemand in den Ranglisten');

        $this->get('/suche?q=' . urlencode('Anonymer'))
            ->assertOk()
            ->assertDontSee('Anonymer Fechter');
    }

    public function test_a_club_that_asked_not_to_be_named_is_not_named(): void
    {
        $shy = Group::create(['name' => 'Fechtschule Musterstadt', 'is_active' => true, 'is_public' => false]);
        $shy->federations()->attach($this->ddhf);

        $this->placed($this->fencer('Vorname', 'Nachnahme', $shy), $this->season(2026), 1, $shy);

        $this->get('/suche?q=Nachnahme')
            ->assertOk()
            ->assertSee('Vorname Nachnahme')
            ->assertDontSee('Fechtschule Musterstadt');
    }

    public function test_the_rank_comes_with_the_size_of_the_field(): void
    {
        $season = $this->season(2026);
        $this->placed($this->fencer('Vorname', 'Nachnahme'), $season, 2);
        $this->placed($this->fencer('Zweiter', 'Fechter'), $season, 1);

        // Fourth of thirty-one is not the same achievement as fourth of five, so the rank never
        // travels without it.
        $this->get('/suche?q=Nachnahme')->assertOk()->assertSee('2. von 2');
    }

    public function test_the_rank_is_written_the_way_each_language_writes_an_ordinal(): void
    {
        $season = $this->season(2026);
        $this->placed($this->fencer('Vorname', 'Nachnahme'), $season, 2);
        $this->placed($this->fencer('Zweiter', 'Fechter'), $season, 1);

        // "2." is not English, it is German punctuation left standing in an English sentence.
        $this->get('/en/search?q=Nachnahme')
            ->assertOk()
            ->assertSee('2nd of 2')
            ->assertDontSee('2. of 2');
    }

    public function test_a_search_engine_is_asked_to_stay_away(): void
    {
        // The standings are indexed and stay indexed. A page that answers to a person's name is a
        // different thing, and a result page has no business in an index anyway.
        $this->get('/suche?q=Nachnahme')
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex">', false);
    }

    public function test_one_letter_is_not_a_search(): void
    {
        $this->placed($this->fencer('Vorname', 'Nachnahme'), $this->season(2026), 1);

        // Otherwise the page is a membership list with a text box on top.
        $this->get('/suche?q=N')
            ->assertOk()
            ->assertDontSee('Vorname Nachnahme')
            ->assertSee('mindestens 2 Zeichen');
    }

    public function test_the_empty_page_lists_nobody(): void
    {
        $this->placed($this->fencer('Vorname', 'Nachnahme'), $this->season(2026), 1);

        $this->get('/suche')->assertOk()->assertDontSee('Vorname Nachnahme');
    }

    public function test_the_english_page_answers_as_well(): void
    {
        $this->placed($this->fencer('Vorname', 'Nachnahme'), $this->season(2026), 1);

        $this->get('/en/search?q=Nachnahme')
            ->assertOk()
            ->assertSee('Find a person')
            ->assertSee('Vorname Nachnahme');
    }

    public function test_the_search_is_reachable_from_the_menu(): void
    {
        $this->get('/')->assertOk()->assertSee('/suche', false);
    }
}
