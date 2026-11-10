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
use App\Standings\ScoringMatrixEvaluator;
use App\Standings\SeasonRanking;
use App\Standings\ScoringMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The table after each event, and the places a fencer moved since the one before - like the table
 * after each matchday in a football league.
 */
class RankingStepsTest extends TestCase
{
    use RefreshDatabase;

    private Season $season;

    private Group $club;

    /** @var array<string, Fencer> */
    private array $fencers = [];

    protected function setUp(): void
    {
        parent::setUp();

        $ddhf = Federation::create([
            'name'         => 'Deutscher Dachverband für historisches Fechten e. V.',
            'abbreviation' => Federation::OWN,
            'is_active'    => true,
        ]);

        $this->club = Group::create(['name' => 'Ochs', 'is_active' => true]);
        $this->club->federations()->attach($ddhf);

        // Scored from four participants up; a smaller field earns nobody anything.
        $matrix = ScoringMatrix::create([
            'name'   => 'Test',
            'matrix' => json_encode([[
                'participants' => ['min' => 4],
                'points'       => [
                    ['place' => ['min' => 1, 'points' => 10]],
                    ['place' => ['min' => 2, 'points' => 6]],
                    ['place' => ['min' => 3, 'points' => 3]],
                    ['place' => ['min' => 4, 'points' => 1]],
                ],
            ]]),
        ]);

        $this->season = Season::create([
            'year'              => 2026,
            'is_public'         => true,
            'standing_id'       => Standing::create([
                'discipline_id' => Discipline::create(['name' => 'Langschwert'])->id,
                'division_id'   => Division::create(['name' => 'offen'])->id,
            ])->id,
            'scoring_matrix_id' => $matrix->id,
            'scoring_mode'      => ScoringMode::Standard,
        ]);
    }

    private function event(string $name, string $date): Event
    {
        return Event::create(['name' => $name, 'start_date' => $date]);
    }

    private function tournament(Event $event, int $participants = 4): Tournament
    {
        return Tournament::create([
            'season_id'         => $this->season->id,
            'event_id'          => $event->id,
            'participant_count' => $participants,
        ]);
    }

    private function fencer(string $last): Fencer
    {
        return $this->fencers[$last] ??= Fencer::create([
            'first_name' => 'Test',
            'last_name'  => $last,
            'is_active'  => true,
        ]);
    }

    /** @param  list<string>  $order  Last names, first place first. */
    private function results(Tournament $tournament, array $order): void
    {
        foreach ($order as $index => $last) {
            Result::create([
                'tournament_id'     => $tournament->id,
                'fencer_id'         => $this->fencer($last)->id,
                'group_id'          => $this->club->id,
                'federation_id'     => $this->club->scoringFederation()?->id,
                'placement'         => $index + 1,
                'fencer_group_name' => $this->club->name,
            ]);
        }
    }

    private function loaded(): Season
    {
        return Season::with(SeasonRanking::relations())->find($this->season->id);
    }

    /** @return array<string, int|null> Last name => change. */
    private function changes(array $rows): array
    {
        $out = [];

        foreach ($rows as $row) {
            $out[$row['fencer']->last_name] = $row['change'];
        }

        return $out;
    }

    public function test_the_evaluator_knows_whether_a_field_is_scored_at_all(): void
    {
        $evaluator = ScoringMatrixEvaluator::fromJson(ScoringMatrix::first()->matrix);

        $this->assertFalse($evaluator->hasBandFor(3));
        $this->assertTrue($evaluator->hasBandFor(4));
        $this->assertTrue($evaluator->hasBandFor(40));
    }

    public function test_one_step_per_event_oldest_first_and_cumulative(): void
    {
        // Created out of order on purpose: the date decides, not the id.
        $march = $this->tournament($this->event('März', '2026-03-01'));
        $january = $this->tournament($this->event('Januar', '2026-01-10'));
        $may = $this->event('Mai', '2026-05-01');
        $may_a = $this->tournament($may);
        $may_b = $this->tournament($may);

        $steps = SeasonRanking::steps($this->loaded());

        $this->assertCount(3, $steps);
        $this->assertSame('10.01.2026 · Januar', $steps[0]['label']);
        $this->assertSame([$january->id], $steps[0]['tournament_ids']);
        $this->assertSame([$january->id, $march->id], $steps[1]['tournament_ids']);
        $this->assertEqualsCanonicalizing(
            [$january->id, $march->id, $may_a->id, $may_b->id],
            $steps[2]['tournament_ids']
        );
        $this->assertSame($may->public_id, $steps[2]['key']);
    }

    public function test_an_unscored_event_is_no_step_but_still_part_of_the_table(): void
    {
        $january = $this->tournament($this->event('Januar', '2026-01-10'));
        $small = $this->tournament($this->event('Klein', '2026-02-01'), participants: 3);
        $march = $this->tournament($this->event('März', '2026-03-01'));
        $late = $this->tournament($this->event('Spät und klein', '2026-06-01'), participants: 2);

        $steps = SeasonRanking::steps($this->loaded());

        $this->assertSame(['10.01.2026 · Januar', '01.03.2026 · März'], array_column($steps, 'label'));
        $this->assertSame([$january->id], $steps[0]['tournament_ids']);
        // The small event in between counts from the next step on, and the last step is the table
        // as it stands - the small event after it included.
        $this->assertSame([$january->id, $small->id, $march->id, $late->id], $steps[1]['tournament_ids']);
    }

    public function test_climbers_fallers_the_unchanged_and_newcomers(): void
    {
        $first = $this->tournament($this->event('Januar', '2026-01-10'));
        $second = $this->tournament($this->event('März', '2026-03-01'));

        // After January: Anna 10, Bert 6, Cleo 3, Dora 1.
        $this->results($first, ['Anna', 'Bert', 'Cleo', 'Dora']);
        // March: Dora 10, Emil (new) 6, Bert 3, Anna 1.
        // Totals: Anna 11, Dora 11, Bert 9, Emil 6, Cleo 3.
        $this->results($second, ['Dora', 'Emil', 'Bert', 'Anna']);

        $season = $this->loaded();
        $steps = SeasonRanking::steps($season);
        $before = SeasonRanking::for($season, $steps[0]['tournament_ids']);
        $now = SeasonRanking::withChanges(SeasonRanking::for($season)['rows'], $before['rows']);

        $this->assertSame([
            'Anna' => 0,     // 1 -> 1 (shared with Dora)
            'Dora' => 3,     // 4 -> 1
            'Bert' => -1,    // 2 -> 3
            'Emil' => null,  // new
            'Cleo' => -2,    // 3 -> 5, pushed down by the newcomer as well
        ], $this->changes($now));
    }

    public function test_an_earlier_step_shows_only_its_own_points(): void
    {
        $first = $this->tournament($this->event('Januar', '2026-01-10'));
        $second = $this->tournament($this->event('März', '2026-03-01'));
        $this->results($first, ['Anna', 'Bert', 'Cleo', 'Dora']);
        $this->results($second, ['Dora', 'Emil', 'Bert', 'Anna']);

        $season = $this->loaded();
        $steps = SeasonRanking::steps($season);
        $rows = SeasonRanking::for($season, $steps[0]['tournament_ids'])['rows'];

        $this->assertSame([10, 6, 3, 1], array_column($rows, 'points'));
        $this->assertSame(1, count($rows[0]['results']));
    }

    public function test_the_page_shows_the_changes_and_the_earlier_tables(): void
    {
        $january = $this->event('Januar', '2026-01-10');
        $this->results($this->tournament($january), ['Anna', 'Bert', 'Cleo', 'Dora']);
        $this->results($this->tournament($this->event('März', '2026-03-01')), ['Dora', 'Emil', 'Bert', 'Anna']);

        $url = route('site.standing', $this->season->public_id);

        $this->get($url)
            ->assertOk()
            ->assertSee('Stand nach')
            ->assertSee('aktuell: 01.03.2026 · März')
            ->assertSee('value="' . $january->public_id . '"', false)
            ->assertSee('▲ 3')
            ->assertSee('▼ 2')
            ->assertSee('class="new"', false);

        // The first step has nothing before it to compare with.
        $this->get($url . '?stand=' . $january->public_id)
            ->assertOk()
            ->assertSee('Stand nach 10.01.2026 · Januar')
            ->assertDontSee('class="change"', false)
            ->assertDontSee('Emil');

        // A step nobody knows is the current table.
        $this->get($url . '?stand=gibtsnicht')
            ->assertOk()
            ->assertSee('Emil')
            ->assertSee('▲ 3');
    }

    public function test_a_standing_with_a_single_event_offers_no_steps(): void
    {
        $this->results($this->tournament($this->event('Januar', '2026-01-10')), ['Anna', 'Bert']);

        $this->get(route('site.standing', $this->season->public_id))
            ->assertOk()
            ->assertDontSee('Stand nach')
            ->assertDontSee('class="change"', false);
    }
}
