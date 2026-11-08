<?php

namespace Tests\Feature\Site;

use App\Models\Discipline;
use App\Models\Division;
use App\Models\ScoringMatrix;
use App\Models\Season;
use App\Models\Standing;
use App\Standings\ScoringMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The page a standing links to for how its points are awarded. Reached from the standing only:
 * no list, no menu entry, no sitemap, noindex.
 */
class ScoringPageTest extends TestCase
{
    use RefreshDatabase;

    private ScoringMatrix $matrix;

    private Season $season;

    protected function setUp(): void
    {
        parent::setUp();

        $this->matrix = ScoringMatrix::create([
            'name'   => 'Punkteschlüssel 2026',
            'matrix' => file_get_contents(base_path('database/scoring-matrices/2026-standard.json')),
        ]);

        $this->season = $this->season($this->matrix, 'Langes Schwert', (int) now()->year);
    }

    private function season(ScoringMatrix $matrix, string $discipline, int $year): Season
    {
        return Season::create([
            'year'              => $year,
            'standing_id'       => Standing::create([
                'discipline_id' => Discipline::firstOrCreate(['name' => $discipline])->id,
                'division_id'   => Division::firstOrCreate(['name' => 'offen'])->id,
            ])->id,
            'scoring_matrix_id' => $matrix->id,
            'scoring_mode'      => ScoringMode::Standard,
        ]);
    }

    public function test_the_standing_names_season_scoring_and_links_the_matrix(): void
    {
        $this->get("/ranglisten/{$this->season->public_id}")
            ->assertOk()
            ->assertSee('<dt>Saison</dt>', false)
            ->assertSee('<dd>' . now()->year . '</dd>', false)
            ->assertSee(ScoringMode::Standard->label())
            ->assertSee('<dt>Punktevergabe</dt>', false)
            ->assertSee('href="' . url("/wertung/{$this->matrix->public_id}") . '"', false)
            ->assertSee('Punkteschlüssel 2026');

        $this->get("/en/standings/{$this->season->public_id}")
            ->assertOk()
            ->assertSee('href="' . url("/en/scoring/{$this->matrix->public_id}") . '"', false);
    }

    public function test_the_matrix_is_laid_out_with_its_rounds_pools_and_threshold(): void
    {
        $this->get("/wertung/{$this->matrix->public_id}")
            ->assertOk()
            ->assertSee('<h1>Punkteschlüssel 2026</h1>', false)
            ->assertSeeInOrder(['Platz', 'Teilnehmende', '5–8', '9–16', 'ab 129'])
            ->assertSeeInOrder(['7–8', '4tel Finale'])
            ->assertSee('>46</td>', false)
            ->assertSee('Ausgeschieden in den Pools')
            ->assertSee('Unter 5 Teilnehmenden gibt es keine Punkte.')
            ->assertSee('href="' . url("/ranglisten/{$this->season->public_id}") . '"', false);

        $this->get("/en/scoring/{$this->matrix->public_id}")
            ->assertOk()
            ->assertSee('Participants')
            ->assertSee('129+')
            ->assertSee('Out in the pools');
    }

    public function test_it_asks_not_to_be_indexed_even_when_the_site_wants_to_be_found(): void
    {
        config(['site.indexable' => true]);

        $this->get("/wertung/{$this->matrix->public_id}")
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex,follow">', false);
    }

    public function test_it_is_not_in_the_sitemap(): void
    {
        config(['site.indexable' => true]);

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertDontSee('/wertung/')
            ->assertDontSee('/scoring/');
    }

    public function test_there_is_no_list_and_no_page_for_what_is_not_shown(): void
    {
        $this->get('/wertung')->assertNotFound();
        $this->get('/en/scoring')->assertNotFound();
        $this->get('/wertung/XXXX')->assertNotFound();

        // Used by a held-back season only: as if it did not exist, like the season itself.
        $held = ScoringMatrix::create(['name' => 'Punkteschlüssel alt', 'matrix' => '[]']);
        $this->season($held, 'Rapier', 2019)->update(['is_public' => false]);
        $this->get("/wertung/{$held->public_id}")->assertNotFound();

        // Used by nobody at all.
        $unused = ScoringMatrix::create(['name' => 'Punkteschlüssel leer', 'matrix' => '[]']);
        $this->get("/wertung/{$unused->public_id}")->assertNotFound();
    }

    public function test_a_held_back_season_is_not_listed_under_a_matrix_it_shares(): void
    {
        $this->season($this->matrix, 'Rapier', 2019)->update(['is_public' => false]);

        $this->get("/wertung/{$this->matrix->public_id}")
            ->assertOk()
            ->assertDontSee('Rapier');
    }

    public function test_a_matrix_that_does_not_decode_is_an_empty_page_not_an_error(): void
    {
        $this->matrix->update(['matrix' => 'kaputt']);

        $this->get("/wertung/{$this->matrix->public_id}")
            ->assertOk()
            ->assertSee('Für diese Punktevergabe ist keine Tabelle hinterlegt.');
    }
}
