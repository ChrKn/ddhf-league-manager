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
use App\Standings\ScoringMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * A season can be held back until it has been released. The site goes live with the current year
 * and the older ones follow one by one.
 *
 * Held back means absent from everything a reader or a consumer can reach - the pages, the filters,
 * the search, the API - including the indirect ways in, through one of its tournaments or through
 * the standing it belongs to.
 */
class SeasonVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private Federation $ddhf;

    private Group $club;

    private Season $shown;

    private Season $held;

    private Tournament $shownTournament;

    private Tournament $heldTournament;

    private string $key;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ddhf = Federation::create([
            'name'         => 'Deutscher Dachverband für historisches Fechten e. V.',
            'abbreviation' => Federation::OWN,
            'is_active'    => true,
        ]);

        $this->club = Group::create(['name' => 'Fechtschule Musterstadt', 'is_active' => true]);
        $this->club->federations()->attach($this->ddhf);

        $matrix = ScoringMatrix::create([
            'name'   => 'Punkteschlüssel Test',
            'matrix' => json_encode([[
                'participants' => ['min' => 0],
                'points'       => [
                    ['place' => ['min' => 1, 'points' => 10]],
                    ['place' => ['min' => 2, 'points' => 6]],
                ],
            ]]),
        ]);

        $open = Division::create(['name' => 'offen']);

        // The released one in this year, the held-back one years earlier and in a standing of its
        // own, so that the year, the weapon and the standing each have something to leak.
        $this->shown = $this->season($matrix, 'Langes Schwert', $open, (int) now()->year);
        $this->held = $this->season($matrix, 'Rapier', $open, 2019);
        $this->held->update(['is_public' => false]);

        $this->shownTournament = $this->tournament($this->shown, 'Musterturnier', now()->startOfYear()->addMonths(2));
        $this->heldTournament = $this->tournament($this->held, 'Verborgenes Turnier', now()->setYear(2019));

        $erika = $this->fencer('Erika', 'Mustermann');
        $this->fence($this->shownTournament, $erika, 1);
        $this->fence($this->heldTournament, $erika, 1);

        $this->fence($this->heldTournament, $this->fencer('Hanna', 'Verborgen'), 2);

        $this->key = ApiKey::create(['name' => 'Test', 'key' => str_repeat('S', 64)])->key;
    }

    private function season(ScoringMatrix $matrix, string $discipline, Division $division, int $year): Season
    {
        return Season::create([
            'year'              => $year,
            'standing_id'       => Standing::create([
                'discipline_id' => Discipline::create(['name' => $discipline])->id,
                'division_id'   => $division->id,
            ])->id,
            'scoring_matrix_id' => $matrix->id,
            'scoring_mode'      => ScoringMode::Standard,
        ]);
    }

    private function tournament(Season $season, string $name, $date): Tournament
    {
        return Tournament::create([
            'season_id'         => $season->id,
            'event_id'          => Event::create(['name' => $name, 'start_date' => $date->toDateString()])->id,
            'participant_count' => 2,
        ]);
    }

    private function fencer(string $first, string $last): Fencer
    {
        return Fencer::create([
            'first_name' => $first,
            'last_name'  => $last,
            'is_active'  => true,
            'group_id'   => $this->club->id,
        ]);
    }

    private function fence(Tournament $tournament, Fencer $fencer, int $placement): void
    {
        Result::create([
            'tournament_id'     => $tournament->id,
            'fencer_id'         => $fencer->id,
            'group_id'          => $this->club->id,
            'federation_id'     => $this->ddhf->id,
            'placement'         => (string) $placement,
            'fencer_group_name' => $this->club->name,
        ]);
    }

    private function api(string $path): TestResponse
    {
        return $this->withToken($this->key)->getJson($path);
    }

    public function test_a_new_season_is_shown_unless_somebody_says_otherwise(): void
    {
        $this->assertTrue($this->shown->refresh()->isPublic());
    }

    public function test_the_list_pages_leave_it_out_along_with_its_year_and_weapon(): void
    {
        $this->get(route('site.standings'))
            ->assertOk()
            ->assertSee('Langes Schwert')
            ->assertDontSee('Rapier')
            ->assertDontSee('2019');

        $this->get(route('site.tournaments'))
            ->assertOk()
            ->assertSee('Musterturnier')
            ->assertDontSee('Verborgenes Turnier')
            ->assertDontSee('Rapier')
            ->assertDontSee('2019');
    }

    public function test_asking_for_its_year_finds_nothing(): void
    {
        $this->get(route('site.standings', ['jahr' => 2019]))
            ->assertOk()
            ->assertDontSee(route('site.standing', $this->held->public_id));

        $this->get(route('site.tournaments', ['jahr' => 2019]))
            ->assertOk()
            ->assertDontSee('Verborgenes Turnier');
    }

    public function test_its_pages_answer_as_if_they_did_not_exist(): void
    {
        $this->get(route('site.standing', $this->held->public_id))->assertNotFound();
        $this->get(route('site.tournament', $this->heldTournament->public_id))->assertNotFound();

        $this->get(route('site.standing', $this->shown->public_id))->assertOk();
        $this->get(route('site.tournament', $this->shownTournament->public_id))->assertOk();
    }

    public function test_the_front_page_leaves_out_a_held_back_season_of_this_year(): void
    {
        $this->get(route('site.index'))->assertOk()->assertSee('Mustermann');

        $this->shown->update(['is_public' => false]);

        $this->get(route('site.index'))->assertOk()->assertDontSee('Mustermann');
    }

    public function test_the_search_finds_nobody_who_is_ranked_only_there(): void
    {
        $this->get('/suche?q=Verborgen')
            ->assertOk()
            ->assertDontSee('Hanna');
    }

    public function test_the_search_names_only_the_released_appearances(): void
    {
        $this->get('/suche?q=Mustermann')
            ->assertOk()
            ->assertSee('Erika')
            ->assertSee('Langes Schwert')
            ->assertDontSee('Rapier');
    }

    public function test_the_api_does_not_list_it(): void
    {
        $ids = collect($this->api('/api/seasons')->assertOk()->json('data'))->pluck('id');

        $this->assertContains($this->shown->public_id, $ids);
        $this->assertNotContains($this->held->public_id, $ids);
    }

    public function test_the_api_answers_404_for_it_and_its_tournaments(): void
    {
        $this->api("/api/seasons/{$this->held->public_id}")->assertNotFound();
        $this->api("/api/seasons/{$this->held->public_id}/standing")->assertNotFound();
        $this->api("/api/tournaments/{$this->heldTournament->public_id}")->assertNotFound();

        $this->api("/api/seasons/{$this->shown->public_id}/standing")->assertOk();
        $this->api("/api/tournaments/{$this->shownTournament->public_id}")->assertOk();
    }

    public function test_a_standing_shows_only_its_released_years(): void
    {
        // A second year for the released standing, held back like the other one.
        $earlier = Season::create([
            'year'              => 2020,
            'standing_id'       => $this->shown->standing_id,
            'scoring_matrix_id' => $this->shown->scoring_matrix_id,
            'is_public'         => false,
        ]);

        $years = collect($this->api("/api/standings/{$this->shown->standing->public_id}")->assertOk()->json('data.seasons'))
            ->pluck('id');

        $this->assertSame([$this->shown->public_id], $years->all());
        $this->assertNotContains($earlier->public_id, $years);
    }

    public function test_a_standing_with_no_released_year_is_not_there_at_all(): void
    {
        $ids = collect($this->api('/api/standings')->assertOk()->json('data'))->pluck('id');

        $this->assertContains($this->shown->standing->public_id, $ids);
        $this->assertNotContains($this->held->standing->public_id, $ids);

        $this->api("/api/standings/{$this->held->standing->public_id}")->assertNotFound();
    }

    public function test_holding_a_season_back_changes_no_points_in_the_ones_that_are_shown(): void
    {
        $before = $this->api("/api/seasons/{$this->shown->public_id}/standing")->json('standing');

        $this->held->update(['is_public' => true]);

        $this->assertSame($before, $this->api("/api/seasons/{$this->shown->public_id}/standing")->json('standing'));
    }
}
