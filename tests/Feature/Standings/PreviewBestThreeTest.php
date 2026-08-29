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
use App\Standings\ScoringMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The preview standing, and the promise that it leaves nothing behind.
 *
 * That promise is the whole reason the command exists rather than a handful of rows added by hand:
 * the live data set is being built for real, and a demonstration standing must not end up in it.
 */
class PreviewBestThreeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        Federation::create([
            'name'         => 'Deutscher Dachverband für historisches Fechten e. V.',
            'abbreviation' => Federation::OWN,
            'is_active'    => true,
        ]);
    }

    /** @return array<string, int> */
    private function census(): array
    {
        return [
            'seasons'     => Season::count(),
            'standings'   => Standing::count(),
            'disciplines' => Discipline::count(),
            'divisions'   => Division::count(),
            'matrices'    => ScoringMatrix::count(),
            'groups'      => Group::count(),
            'fencers'     => Fencer::count(),
            'events'      => Event::count(),
            'tournaments' => Tournament::count(),
            'results'     => Result::count(),
        ];
    }

    public function test_it_builds_a_season_where_three_of_five_results_count(): void
    {
        $this->artisan('standings:preview-best-three')->assertSuccessful();

        $season = Season::sole();

        $this->assertSame(ScoringMode::BestThree, $season->scoring_mode);
        $this->assertSame(5, $season->tournaments()->count());
        $this->assertSame(15, Result::count());

        $html = $this->get('/ranglisten/' . $season->public_id)->assertOk()->getContent();

        // Every one of the three fencers has five results and three that count, so each row shows
        // two greyed and one rule.
        $this->assertSame(3, substr_count($html, 'class="not-counted"'));
        $this->assertSame(3, substr_count($html, 'class="not-counted cut"'));
        $this->assertSame(3, substr_count($html, '· 3 gewertet'));
    }

    public function test_removing_it_puts_the_database_back_exactly_as_it_was(): void
    {
        $before = $this->census();

        $this->artisan('standings:preview-best-three')->assertSuccessful();
        $this->assertNotSame($before, $this->census());

        $this->artisan('standings:preview-best-three --remove')->assertSuccessful();

        // Not "roughly the same" - every table it touched, back to the number it started at.
        $this->assertSame($before, $this->census());
    }

    public function test_it_refuses_to_stand_twice(): void
    {
        $this->artisan('standings:preview-best-three')->assertSuccessful();
        $this->artisan('standings:preview-best-three')->assertFailed();

        $this->assertSame(1, Season::count());
    }

    public function test_removing_what_was_never_made_does_nothing(): void
    {
        $before = $this->census();

        $this->artisan('standings:preview-best-three --remove')->assertFailed();

        $this->assertSame($before, $this->census());
    }

    public function test_without_the_federation_it_refuses_rather_than_showing_an_empty_table(): void
    {
        Federation::query()->delete();

        $this->artisan('standings:preview-best-three')->assertFailed();

        $this->assertSame(0, Season::count());
    }
}
