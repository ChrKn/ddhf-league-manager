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
use App\Standings\SeasonRanking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Which results a total is made of, where not all of them are.
 *
 * Best Three adds up a fencer's three best tournaments and drops the rest. Until this was shown,
 * the page listed all five in the order the tournaments happened to be fenced in and said "zählt
 * nicht" beside two of them - which left the reader adding numbers up to find out why somebody
 * with five results was behind somebody with three.
 *
 * The federation has never yet fenced more than two tournaments in one season, so this case does
 * not exist in the live data and cannot be seen there. It is built here instead, and
 * `standings:preview-best-three` builds it in the database for as long as somebody wants to look
 * at it.
 */
class CountedResultsTest extends TestCase
{
    use RefreshDatabase;

    private Federation $ddhf;

    private Group $club;

    private ScoringMatrix $matrix;

    private Discipline $discipline;

    protected function setUp(): void
    {
        parent::setUp();

        // The data browser is part of the admin panel now.
        $this->actingAsMaintainer();

        $this->ddhf = Federation::create([
            'name'         => 'Deutscher Dachverband für historisches Fechten e. V.',
            'abbreviation' => Federation::OWN,
            'is_active'    => true,
        ]);

        $this->club = Group::create([
            'name'          => 'Schildwache Potsdam',
            'is_active'     => true,
        ]);

        $this->club->federations()->attach($this->ddhf);

        // One point per place away from twenty-first, so a placement reads straight off the score
        // and back again: first is 20, fifth is 16.
        $this->matrix = ScoringMatrix::create([
            'name'   => 'Punkteschlüssel Test',
            'matrix' => json_encode([[
                'participants' => ['min' => 1],
                'points'       => array_map(
                    fn (int $place) => ['place' => ['min' => $place, 'points' => 21 - $place]],
                    range(1, 20),
                ),
            ]]),
        ]);

        $this->discipline = Discipline::create(['name' => 'Langes Schwert']);
    }

    private function season(ScoringMode $mode, string $division = 'offen'): Season
    {
        return Season::create([
            'year'              => 2026,
            'standing_id'       => Standing::create([
                'discipline_id' => $this->discipline->id,
                'division_id'   => Division::create(['name' => $division])->id,
            ])->id,
            'scoring_matrix_id' => $this->matrix->id,
            'scoring_mode'      => $mode,
        ]);
    }

    private function fencer(string $first = 'Test'): Fencer
    {
        return Fencer::create([
            'first_name' => $first,
            'last_name'  => 'Fechterin',
            'is_active'  => true,
            'group_id'   => $this->club->id,
        ]);
    }

    private function placed(
        Season $season,
        Fencer $fencer,
        string $tournament,
        int $place,
        ?string $region = null,
    ): void {
        $created = Tournament::create([
            'season_id'         => $season->id,
            'event_id'          => Event::create(['name' => $tournament, 'start_date' => '2026-03-07'])->id,
            'participant_count' => 20,
            'region'            => $region,
        ]);

        Result::create([
            'tournament_id' => $created->id,
            'fencer_id'     => $fencer->id,
            'group_id'      => $this->club->id,
            'federation_id' => $this->ddhf->id,
            'placement'     => (string) $place,
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function results(Season $season): array
    {
        $ranking = SeasonRanking::for($season->fresh()->load(SeasonRanking::relations()));

        return $ranking['rows'][0]['results'];
    }

    /**
     * The names as the results carry them: a tournament is shown under its standing and year with
     * the event in brackets, and the test says which events it means.
     *
     * @param  list<string>  $events
     * @return list<string>
     */
    private function named(array $events): array
    {
        return array_map(fn (string $event) => "Langes Schwert offen 2026 ({$event})", $events);
    }

    /** Five tournaments, placed so that no two are worth the same. */
    private function fiveTournaments(Season $season): Fencer
    {
        $fencer = $this->fencer();

        $this->placed($season, $fencer, 'Drittes', 3);
        $this->placed($season, $fencer, 'Zwölftes', 12);
        $this->placed($season, $fencer, 'Erstes', 1);
        $this->placed($season, $fencer, 'Achtes', 8);
        $this->placed($season, $fencer, 'Zweites', 2);

        return $fencer;
    }

    public function test_the_three_that_count_come_first_and_best_first(): void
    {
        $season = $this->season(ScoringMode::BestThree);
        $this->fiveTournaments($season);

        $results = $this->results($season);

        // Read in order: the total is the first three added up, and the reader can see that
        // without knowing which system the season runs on.
        $this->assertSame(
            $this->named(['Erstes', 'Zweites', 'Drittes', 'Achtes', 'Zwölftes']),
            array_column($results, 'tournament'),
        );
        $this->assertSame([true, true, true, false, false], array_column($results, 'counts'));
    }

    public function test_the_counted_ones_add_up_to_the_total(): void
    {
        $season = $this->season(ScoringMode::BestThree);
        $this->fiveTournaments($season);

        $ranking = SeasonRanking::for($season->fresh()->load(SeasonRanking::relations()));
        $row = $ranking['rows'][0];

        $counted = array_filter($row['results'], fn ($result) => $result['counts']);

        $this->assertSame($row['points'], array_sum(array_column($counted, 'points')));
        $this->assertSame(20 + 19 + 18, $row['points']);
    }

    public function test_two_results_worth_the_same_keep_a_fixed_order(): void
    {
        $season = $this->season(ScoringMode::BestThree);
        $fencer = $this->fencer();

        // A rank and a round can be worth the same number of points. Without a second criterion
        // the two would swap places between page loads, which reads as data moving on its own.
        $this->placed($season, $fencer, 'Turnierbaum', 4);
        $this->placed($season, $fencer, 'Rundenlauf', 4);

        $this->assertSame(
            $this->named(['Rundenlauf', 'Turnierbaum']),
            array_column($this->results($season), 'tournament'),
        );
    }

    public function test_the_standard_system_counts_everything_and_says_nothing(): void
    {
        $season = $this->season(ScoringMode::Standard);
        $this->fiveTournaments($season);

        $results = $this->results($season);

        $this->assertSame([true, true, true, true, true], array_column($results, 'counts'));

        // Sorted all the same, which is an improvement of its own: the list used to arrive in the
        // order the tournaments were created in.
        $this->assertSame(
            $this->named(['Erstes', 'Zweites', 'Drittes', 'Achtes', 'Zwölftes']),
            array_column($results, 'tournament'),
        );
    }

    public function test_the_zone_system_puts_the_winning_zone_on_top_not_the_best_result(): void
    {
        $season = $this->season(ScoringMode::Zone);
        $fencer = $this->fencer();

        // North has two results worth 19 and 18; the single best result of the season is the 20
        // in the south and does not count for anything.
        $this->placed($season, $fencer, 'Süden', 1, 'south');
        $this->placed($season, $fencer, 'Norden', 2, 'north');
        $this->placed($season, $fencer, 'Nordosten', 3, 'north');

        $results = $this->results($season);

        $this->assertSame($this->named(['Norden', 'Nordosten', 'Süden']), array_column($results, 'tournament'));
        $this->assertSame([true, true, false], array_column($results, 'counts'));
    }

    public function test_the_page_greys_out_what_does_not_count_and_sets_it_apart(): void
    {
        $season = $this->season(ScoringMode::BestThree);
        $this->fiveTournaments($season);

        $html = $this->get('/ranglisten/' . $season->public_id)->assertOk()->getContent();

        // Two rows greyed, and exactly one rule between the block that counts and the block that
        // does not. Counted on the attribute rather than the word, which is in the stylesheet too.
        $this->assertSame(1, substr_count($html, 'class="not-counted"'));
        $this->assertSame(1, substr_count($html, 'class="not-counted cut"'));
        $this->assertStringContainsString('· 3 gewertet', $html);
    }

    public function test_the_english_page_says_it_in_english(): void
    {
        $season = $this->season(ScoringMode::BestThree);
        $this->fiveTournaments($season);

        $this->get('/en/standings/' . $season->public_id)
            ->assertOk()
            ->assertSee('· 3 counted')
            ->assertSee('does not count');
    }

    public function test_a_season_where_everything_counts_gets_no_rule_and_no_note(): void
    {
        $season = $this->season(ScoringMode::Standard);
        $this->fiveTournaments($season);

        $html = $this->get('/ranglisten/' . $season->public_id)->assertOk()->getContent();

        $this->assertStringNotContainsString('class="not-counted', $html);
        $this->assertStringNotContainsString('cut"', $html);
        $this->assertStringNotContainsString('<span class="of-which">', $html);
    }

    public function test_the_data_browser_tells_the_same_story(): void
    {
        $season = $this->season(ScoringMode::BestThree);
        $this->fiveTournaments($season);

        $html = $this->get(\App\Filament\Pages\Browse\Ranking::getUrl(['public_id' => $season->public_id]))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'class="not-counted"'));
        $this->assertSame(1, substr_count($html, 'class="not-counted cut"'));
    }
}
