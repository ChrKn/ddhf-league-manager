<?php

namespace Tests\Feature;

use App\Models\Discipline;
use App\Models\Division;
use App\Models\Event;
use App\Models\ScoringMatrix;
use App\Models\Season;
use App\Models\Standing;
use App\Models\Tournament;
use App\Standings\ScoringMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * When a tournament was fenced.
 *
 * Most take the date from their event, which is right until the event runs over several days -
 * and 26 of 45 do, some with six tournaments spread across them. Sabre on the Saturday and
 * longsword on the Sunday are two dates, and a preliminary round on the first day with the final
 * on the second is a span.
 *
 * The rule the tests hold to: a tournament that states its own dates speaks for itself and the
 * event is not consulted at all; one that says nothing takes the event's.
 */
class TournamentDatesTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    private Season $season;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = Event::create([
            'name'       => 'Musterturnier',
            'start_date' => '2026-05-09',
            'end_date'   => '2026-05-10',
        ]);

        $this->season = Season::create([
            'year'              => 2026,
            'standing_id'       => Standing::create([
                'discipline_id' => Discipline::create(['name' => 'Langes Schwert'])->id,
                'division_id'   => Division::create(['name' => 'offen'])->id,
            ])->id,
            'scoring_matrix_id' => ScoringMatrix::create([
                'name'   => 'Test',
                'matrix' => json_encode([['participants' => ['min' => 0], 'points' => [['place' => ['min' => 1, 'points' => 1]]]]]),
            ])->id,
            'scoring_mode' => ScoringMode::Standard,
        ]);
    }

    private function tournament(array $dates = []): Tournament
    {
        return Tournament::create($dates + [
            'season_id'         => $this->season->id,
            'event_id'          => $this->event->id,
            'participant_count' => 4,
        ]);
    }

    public function test_a_tournament_without_dates_takes_the_events(): void
    {
        $tournament = $this->tournament();

        $this->assertFalse($tournament->hasOwnDates());
        $this->assertSame('2026-05-09', $tournament->held_from->toDateString());
        $this->assertSame('2026-05-10', $tournament->held_to->toDateString());
    }

    public function test_a_tournament_with_its_own_day_ignores_the_event(): void
    {
        // The case this was built for: the event runs Saturday to Sunday, this one was fenced on
        // the Sunday. It must not inherit the Saturday, and it must not be read as a two-day
        // tournament running to the end of the event.
        $tournament = $this->tournament(['start_date' => '2026-05-10']);

        $this->assertSame('2026-05-10', $tournament->held_from->toDateString());
        $this->assertSame('2026-05-10', $tournament->held_to->toDateString());
        $this->assertFalse($tournament->spansSeveralDays());
    }

    public function test_a_tournament_may_state_a_span_of_its_own(): void
    {
        // Preliminary round on the first day, final on the second.
        $tournament = $this->tournament(['start_date' => '2026-05-09', 'end_date' => '2026-05-10']);

        $this->assertSame('2026-05-09', $tournament->held_from->toDateString());
        $this->assertSame('2026-05-10', $tournament->held_to->toDateString());
        $this->assertTrue($tournament->spansSeveralDays());
    }

    public function test_only_an_end_date_still_answers(): void
    {
        $tournament = $this->tournament(['end_date' => '2026-05-10']);

        $this->assertSame('2026-05-10', $tournament->held_from->toDateString());
        $this->assertSame('2026-05-10', $tournament->held_to->toDateString());
    }

    public function test_an_event_without_an_end_date_falls_back_to_its_start(): void
    {
        $oneDay = Event::create(['name' => 'Eintagsturnier', 'start_date' => '2026-06-01']);
        $tournament = Tournament::create([
            'season_id'         => $this->season->id,
            'event_id'          => $oneDay->id,
            'participant_count' => 4,
        ]);

        $this->assertSame('2026-06-01', $tournament->held_from->toDateString());
        $this->assertSame('2026-06-01', $tournament->held_to->toDateString());
        $this->assertFalse($tournament->spansSeveralDays());
    }

    public function test_the_public_page_shows_a_span_only_where_the_tournament_states_one(): void
    {
        $inherited = $this->tournament();
        $own = $this->tournament(['start_date' => '2026-05-09', 'end_date' => '2026-05-10']);

        // Inherited from a two-day event: one date. Printing the event's span against each of its
        // tournaments would claim something nobody established.
        $this->get('/ddhf-turniere/' . $inherited->public_id)
            ->assertOk()
            ->assertSee('09.05.2026')
            ->assertDontSee('09.05.2026 – 10.05.2026');

        $this->get('/ddhf-turniere/' . $own->public_id)
            ->assertOk()
            ->assertSee('09.05.2026 – 10.05.2026');
    }

    public function test_the_list_sorts_by_the_day_it_was_fenced(): void
    {
        $sunday = $this->tournament(['start_date' => '2026-05-10']);

        $this->get('/ddhf-turniere')
            ->assertOk()
            ->assertSee('data-sort="2026-05-10"', false);

        $this->assertSame('2026-05-10', $sunday->held_from->toDateString());
    }
}
