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
 * The site readers see, as opposed to the browser under /tests.
 *
 * Two promises are worth a test each because both are easy to break by accident: no record id
 * ever appears as text, and the front page stops after ten rows even when the tenth and eleventh
 * are level on points.
 */
class PublicSiteTest extends TestCase
{
    use RefreshDatabase;

    private Federation $ddhf;

    private Group $club;

    private Season $season;

    private Tournament $tournament;

    protected function setUp(): void
    {
        parent::setUp();

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

        $this->season = $this->seasonFor('Langes Schwert', 'offen', (int) now()->year);

        $this->tournament = Tournament::create([
            'season_id'         => $this->season->id,
            'event_id'          => Event::create([
                'name'       => 'Musterturnier',
                'location'   => 'Musterstadt',
                'start_date' => now()->startOfYear()->addMonths(3)->toDateString(),
            ])->id,
            'participant_count' => 12,
            'format'            => 'Turnierbaum',
        ]);
    }

    private function seasonFor(string $discipline, string $division, int $year): Season
    {
        // Places one to four are worth 10, 8, 6 and 4; everything from the fifth is worth one.
        // That makes the rows around the cut level on points, which is the case worth testing.
        $matrix = ScoringMatrix::create([
            'name'   => 'Punkteschlüssel Test',
            'matrix' => json_encode([[
                'participants' => ['min' => 0],
                'points'       => [
                    ['place' => ['min' => 1, 'points' => 10]],
                    ['place' => ['min' => 2, 'points' => 8]],
                    ['place' => ['min' => 3, 'points' => 6]],
                    ['place' => ['min' => 4, 'points' => 4]],
                    ['place' => ['min' => 5, 'points' => 1]],
                ],
            ]]),
        ]);

        return Season::create([
            'year'              => $year,
            'standing_id'       => Standing::create([
                'discipline_id' => Discipline::firstOrCreate(['name' => $discipline])->id,
                'division_id'   => Division::firstOrCreate(['name' => $division])->id,
            ])->id,
            'scoring_matrix_id' => $matrix->id,
            'scoring_mode'      => ScoringMode::Standard,
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

    private function addResult(Fencer $fencer, int|string $placement, ?Tournament $tournament = null): Result
    {
        return Result::create([
            'tournament_id'     => ($tournament ?? $this->tournament)->id,
            'fencer_id'         => $fencer->id,
            'group_id'          => $this->club->id,
            'federation_id'     => $this->ddhf->id,
            'placement'         => (string) $placement,
            'fencer_group_name' => $this->club->name,
        ]);
    }

    /**
     * Twelve fencers, so that places five to twelve are all level on one point.
     *
     * Lettered rather than numbered, because entries level on points are ordered by name and
     * "Fechter10" sorts before "Fechter5" - which would make the test look like a cut in the
     * wrong place when it is only alphabetical order.
     */
    private function fillTheField(): void
    {
        foreach (str_split('ABCDEFGHIJKL') as $index => $letter) {
            $this->addResult($this->fencer('Muster', 'Fechter ' . $letter), $index + 1);
        }
    }

    /** Only the rows of the first table, so a filter dropdown cannot answer for the page. */
    private function tableBody(string $html): string
    {
        preg_match('#<tbody>(.*?)</tbody>#s', $html, $body);

        return $body[1] ?? '';
    }

    public function test_every_page_answers(): void
    {
        $this->fillTheField();

        foreach ([
            route('site.index'),
            route('site.standings'),
            route('site.standing', $this->season->public_id),
            route('site.tournaments'),
            route('site.tournament', $this->tournament->public_id),
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_the_front_page_stops_after_ten_even_when_the_rows_are_level(): void
    {
        $this->fillTheField();

        $body = $this->tableBody($this->get(route('site.index'))->assertOk()->getContent());

        // Places 5 to 12 all score one point, so a soft cut would spill over into eleven or
        // twelve rows. Exactly ten come back, and the two the ordering leaves out stay out.
        $this->assertSame(10, substr_count($body, '<tr>'));
        $this->assertSame(2, 12 - substr_count($body, 'Muster Fechter'));
    }

    public function test_the_front_page_shows_only_the_current_year_and_only_what_has_results(): void
    {
        $this->fillTheField();

        // A standing of the same year without a single result, and one of the year before with
        // results. Neither belongs on the front page.
        $this->seasonFor('Rapier', 'offen', (int) now()->year);

        $lastYear = $this->seasonFor('Säbel', 'offen', (int) now()->year - 1);
        $older = Tournament::create([
            'season_id'         => $lastYear->id,
            'event_id'          => Event::create(['name' => 'Altes Turnier', 'start_date' => now()->subYear()->toDateString()])->id,
            'participant_count' => 4,
        ]);
        $this->addResult($this->fencer('Otto', 'Probstett'), 1, $older);

        $html = $this->get(route('site.index'))->assertOk()->getContent();

        $this->assertStringContainsString('Langes Schwert offen', $html);
        $this->assertStringNotContainsString('Rapier offen', $html);
        $this->assertStringNotContainsString('Otto Probstett', $html);
    }

    public function test_no_page_prints_a_record_id(): void
    {
        $this->fillTheField();

        $ids = [
            $this->season->public_id,
            $this->tournament->public_id,
            $this->club->public_id,
            Fencer::first()->public_id,
        ];

        foreach ([
            route('site.index'),
            route('site.standings'),
            route('site.standing', $this->season->public_id),
            route('site.tournaments'),
            route('site.tournament', $this->tournament->public_id),
        ] as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            // Attributes carry the ids, so they are stripped before looking: the promise is that
            // no id is readable on the page, not that none is in the markup.
            $visible = strip_tags(preg_replace('#<(script|style)[^>]*>.*?</\1>#si', ' ', $html));

            foreach ($ids as $id) {
                $this->assertStringNotContainsString($id, $visible, "Die ID {$id} steht sichtbar auf {$url}");
            }
        }
    }

    public function test_the_menu_offers_no_admin_panel_and_no_record_lists(): void
    {
        $html = $this->get(route('site.index'))->assertOk()->getContent();

        preg_match('#<nav class="main".*?</nav>#s', $html, $nav);

        $this->assertStringNotContainsString('verwaltung', strtolower($nav[0]));
        foreach (['Verbände', 'Vereine', 'Fechter', 'Veranstaltungen', 'Saisons'] as $absent) {
            $this->assertStringNotContainsString($absent, $nav[0]);
        }
    }

    public function test_the_standing_can_be_filtered_by_year_and_discipline(): void
    {
        $this->fillTheField();
        $this->seasonFor('Rapier', 'Damen+', (int) now()->year - 1);

        // Against the table body, not the whole page: every discipline is also an option in the
        // filter dropdown, so assertDontSee on the page would find it there and prove nothing.
        $all = $this->tableBody($this->get(route('site.standings'))->assertOk()->getContent());
        $this->assertStringContainsString('Langes Schwert', $all);
        $this->assertStringContainsString('Rapier', $all);

        $rapier = $this->tableBody(
            $this->get(route('site.standings', ['disziplin' => 'Rapier']))->assertOk()->getContent()
        );
        $this->assertStringContainsString('Rapier', $rapier);
        $this->assertStringNotContainsString('Langes Schwert', $rapier);

        $thisYear = $this->tableBody(
            $this->get(route('site.standings', ['jahr' => now()->year]))->assertOk()->getContent()
        );
        $this->assertStringContainsString('Langes Schwert', $thisYear);
        $this->assertStringNotContainsString('Rapier', $thisYear);
    }

    public function test_a_tournament_is_named_and_reachable_from_the_list(): void
    {
        $this->fillTheField();

        $this->get(route('site.tournaments'))
            ->assertOk()
            ->assertSee('Musterturnier')
            ->assertSee(route('site.tournament', $this->tournament->public_id), false);

        $this->get(route('site.tournament', $this->tournament->public_id))
            ->assertOk()
            ->assertSee('Musterstadt')
            ->assertSee('Turnierbaum')
            ->assertSee('Muster Fechter A');
    }

    public function test_the_scripts_come_after_the_tables_they_reach_into(): void
    {
        $this->fillTheField();

        // The bug this exists for: the search script used to sit next to the filter form, above
        // its table. getElementById returned null and the box did nothing, on every page, with
        // no error to see.
        foreach ([route('site.standings'), route('site.tournaments')] as $url) {
            $html = $this->get($url)->assertOk()->getContent();

            $table = strrpos($html, '</table>');
            $script = strpos($html, 'data-search');
            $wiring = strrpos($html, 'input.dataset.search');

            $this->assertNotFalse($wiring, "Kein Suchskript auf {$url}");
            $this->assertGreaterThan($table, $wiring, "Das Suchskript steht vor der Tabelle auf {$url}");
            $this->assertNotFalse($script);
        }
    }

    public function test_the_tables_are_marked_up_for_sorting(): void
    {
        $this->fillTheField();

        foreach ([
            route('site.standings'),
            route('site.tournaments'),
            route('site.standing', $this->season->public_id),
            route('site.tournament', $this->tournament->public_id),
        ] as $url) {
            $this->assertStringContainsString('data-sortable', $this->get($url)->assertOk()->getContent(), $url);
        }

        // Columns whose text would sort wrongly carry the value to sort by instead: a date by
        // day rather than by the digits it starts with, a round by where it sits in the bracket.
        $this->assertStringContainsString(
            'data-sort="' . now()->startOfYear()->addMonths(3)->format('Y-m-d') . '"',
            $this->get(route('site.tournaments'))->getContent(),
        );

        $this->assertStringContainsString(
            'data-sort="1"',
            $this->get(route('site.tournament', $this->tournament->public_id))->getContent(),
        );
    }

    public function test_the_single_views_can_be_narrowed_to_one_club(): void
    {
        $other = Group::create([
            'name'          => 'Fechtschule Musterstadt',
            'country'       => 'DE',
            'is_active'     => true,
        ]);

        $other->federations()->attach($this->ddhf);

        $this->addResult($this->fencer('Muster', 'Fechter A'), 1);
        $mine = $this->fencer('Otto', 'Probstett', $other);
        Result::create([
            'tournament_id' => $this->tournament->id,
            'fencer_id'     => $mine->id,
            'group_id'      => $other->id,
            'federation_id' => $this->ddhf->id,
            'placement'     => '2',
        ]);

        foreach ([
            route('site.standing', $this->season->public_id),
            route('site.tournament', $this->tournament->public_id),
        ] as $url) {
            $this->get($url)->assertOk()->assertSee('Muster Fechter A')->assertSee('Otto Probstett');

            $narrowed = $this->tableBody(
                $this->get($url . '?verein=' . urlencode($other->name))->assertOk()->getContent()
            );

            $this->assertStringContainsString('Otto Probstett', $narrowed);
            $this->assertStringNotContainsString('Muster Fechter A', $narrowed);
        }
    }

    public function test_narrowing_to_a_club_leaves_the_ranks_alone(): void
    {
        $other = Group::create([
            'name'          => 'Fechtschule Musterstadt',
            'country'       => 'DE',
            'is_active'     => true,
        ]);

        $other->federations()->attach($this->ddhf);

        $this->addResult($this->fencer('Muster', 'Fechter A'), 1);
        $this->addResult($this->fencer('Muster', 'Fechter B'), 2);

        $third = $this->fencer('Otto', 'Probstett', $other);
        Result::create([
            'tournament_id' => $this->tournament->id,
            'fencer_id'     => $third->id,
            'group_id'      => $other->id,
            'federation_id' => $this->ddhf->id,
            'placement'     => '3',
        ]);

        $body = $this->tableBody($this->get(
            route('site.standing', $this->season->public_id) . '?verein=' . urlencode($other->name)
        )->assertOk()->getContent());

        // Third stays third. Renumbering a club's fencers one, two, three would be a different
        // and untrue statement about where they finished.
        $this->assertStringContainsString('>3</td>', $body);
        $this->assertStringNotContainsString('>1</td>', $body);
    }

    public function test_an_anonymised_entry_keeps_its_rank_and_gives_nothing_away(): void
    {
        // Her own club, so that finding its name on the page can only come from her row.
        $herClub = Group::create([
            'name'          => 'Fechtschule Musterstadt',
            'country'       => 'DE',
            'is_active'     => true,
        ]);

        $herClub->federations()->attach($this->ddhf);

        $anonymous = $this->fencer('Erika', 'Mustermann', $herClub);
        $this->addResult($anonymous, 1);
        $this->addResult($this->fencer('Otto', 'Probstett'), 2);
        AnonymizeFencerAction::anonymize($anonymous);

        foreach ([route('site.index'), route('site.standing', $this->season->public_id)] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee(Fencer::ANONYMOUS_NAME)
                ->assertDontSee('Erika Mustermann')
                // The club is the last identifying trait, and it must not come back through the
                // club column of a row that has lost everything else.
                ->assertDontSee('Fechtschule Musterstadt');
        }

        // The tournament keeps its full field, because a placement is a fact about the event.
        $this->get(route('site.tournament', $this->tournament->public_id))
            ->assertOk()
            ->assertSee(Fencer::ANONYMOUS_NAME)
            ->assertDontSee('Erika Mustermann');
    }
}
