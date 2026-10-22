<?php

namespace Tests\Feature\Site;

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
use App\Standings\Placement;
use App\Standings\ScoringMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The public pages once more as JSON - and only that. What is worth testing is not that JSON comes
 * out, but that it says neither more nor less than the page it stands for.
 */
class DataTest extends TestCase
{
    use RefreshDatabase;

    private Season $season;

    private Tournament $tournament;

    protected function setUp(): void
    {
        parent::setUp();

        $ddhf = Federation::create([
            'name'         => 'Deutscher Dachverband für historisches Fechten e. V.',
            'abbreviation' => Federation::OWN,
            'is_active'    => true,
        ]);

        $club = Group::create(['name' => 'Ochs Historische Kampfkünste', 'is_active' => true]);
        $club->federations()->attach($ddhf);

        $this->season = Season::create([
            'year'              => (int) now()->year,
            'standing_id'       => Standing::create([
                'discipline_id' => Discipline::create(['name' => 'Langes Schwert'])->id,
                'division_id'   => Division::create(['name' => 'offen'])->id,
            ])->id,
            'scoring_matrix_id' => ScoringMatrix::create([
                'name'   => 'Punkteschlüssel Test',
                'matrix' => json_encode([[
                    'participants' => ['min' => 0],
                    'points'       => [
                        ['place' => ['min' => 1, 'points' => 10]],
                        ['place' => ['min' => 2, 'points' => 6]],
                        ['place' => ['min' => 3, 'points' => 3]],
                    ],
                ]]),
            ])->id,
            'scoring_mode'      => ScoringMode::Standard,
        ]);

        $this->tournament = Tournament::create([
            'season_id'         => $this->season->id,
            'event_id'          => Event::create([
                'name'       => 'Musterturnier',
                'start_date' => now()->startOfYear()->addMonths(2)->toDateString(),
            ])->id,
            'participant_count' => 3,
        ]);

        $this->fence($club, 'Erika', 'Mustermann', 1);
        $this->fence($club, 'Otto', 'Probstett', 2);
        $this->fence($club, 'Hanna', 'Verborgen', 3)->update(['anonymized_at' => now()]);
    }

    private function fence(Group $club, string $first, string $last, int $placement): Fencer
    {
        $fencer = Fencer::create([
            'first_name' => $first,
            'last_name'  => $last,
            'is_active'  => true,
            'group_id'   => $club->id,
        ]);

        Result::create([
            'tournament_id'     => $this->tournament->id,
            'fencer_id'         => $fencer->id,
            'group_id'          => $club->id,
            'federation_id'     => $club->scoringFederation()?->id,
            'placement'         => (string) $placement,
            'fencer_group_name' => $club->name,
        ]);

        return $fencer;
    }

    /** @return list<string> */
    private function paths(): array
    {
        return [
            '/llms.txt',
            '/data/standings.json',
            "/data/standings/{$this->season->public_id}.json",
            '/data/tournaments.json',
            "/data/tournaments/{$this->tournament->public_id}.json",
        ];
    }

    public function test_none_of_it_exists_while_the_site_is_not_meant_to_be_found(): void
    {
        config(['site.indexable' => false]);

        foreach ($this->paths() as $path) {
            $this->get($path)->assertNotFound();
        }

        // And no page points at an address that answers 404.
        $this->get("/ranglisten/{$this->season->public_id}")
            ->assertOk()
            ->assertDontSee('type="application/json"', false);
    }

    public function test_it_is_there_without_a_session_once_the_site_is_meant_to_be_found(): void
    {
        config(['site.indexable' => true]);

        foreach ($this->paths() as $path) {
            $this->get($path)->assertOk()->assertCookieMissing('laravel_session');
        }

        $this->get('/llms.txt')->assertHeader('Content-Type', 'text/markdown; charset=utf-8');
        $this->get('/data/standings.json')->assertHeader('Content-Type', 'application/json');
    }

    public function test_both_languages_of_a_page_point_at_the_same_document(): void
    {
        config(['site.indexable' => true]);

        $json = "http://localhost/data/standings/{$this->season->public_id}.json";

        foreach (["/ranglisten/{$this->season->public_id}", "/en/standings/{$this->season->public_id}"] as $page) {
            $this->get($page)->assertSee(
                '<link rel="alternate" type="application/json" href="' . $json . '">',
                false
            );
        }
    }

    public function test_a_standing_says_what_its_page_says(): void
    {
        config(['site.indexable' => true]);

        $rows = $this->get("/data/standings/{$this->season->public_id}.json")->assertOk()->json('rows');
        $page = $this->get("/ranglisten/{$this->season->public_id}")->assertOk();

        $this->assertSame(
            [[1, 'Erika Mustermann', 10], [2, 'Otto Probstett', 6], [3, Fencer::ANONYMOUS_NAME, 3]],
            array_map(fn (array $row) => [$row['rank'], $row['name'], $row['points']], $rows)
        );

        foreach ($rows as $row) {
            $page->assertSee($row['name']);
        }
    }

    public function test_an_anonymised_entry_keeps_its_place_and_loses_the_rest(): void
    {
        config(['site.indexable' => true]);

        $standing = $this->get("/data/standings/{$this->season->public_id}.json");
        $tournament = $this->get("/data/tournaments/{$this->tournament->public_id}.json");

        $row = $standing->json('rows.2');
        $this->assertTrue($row['anonymized']);
        $this->assertNull($row['group']);
        $this->assertSame([], $row['results']);

        $result = $tournament->json('results.2');
        $this->assertSame(Fencer::ANONYMOUS_NAME, $result['name']);
        $this->assertNull($result['group']);

        foreach ([$standing, $tournament] as $response) {
            $response->assertDontSee('Verborgen');
        }
    }

    public function test_a_placement_comes_as_its_value_and_both_words(): void
    {
        config(['site.indexable' => true]);

        $this->assertSame(
            ['code' => '1', 'de' => Placement::from('1')->label('de'), 'en' => Placement::from('1')->label('en')],
            $this->get("/data/tournaments/{$this->tournament->public_id}.json")->json('results.0.placement')
        );
    }

    public function test_no_identifier_is_handed_out_as_a_field(): void
    {
        config(['site.indexable' => true]);

        foreach (array_slice($this->paths(), 1) as $path) {
            $keys = [];
            $document = $this->get($path)->json();

            array_walk_recursive($document, function ($value, $key) use (&$keys) {
                $keys[] = $key;
            });

            $this->assertNotContains('id', $keys, $path);
            $this->assertNotContains('public_id', $keys, $path);
        }
    }

    public function test_llms_txt_points_at_the_data(): void
    {
        config(['site.indexable' => true]);

        $this->get('/llms.txt')
            ->assertSee('http://localhost/data/standings.json')
            ->assertSee('http://localhost/data/tournaments.json')
            ->assertSee('http://localhost/en/standings');
    }
}
