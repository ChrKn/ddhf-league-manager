<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Tournaments\Pages\CreateTournament;
use App\Models\Discipline;
use App\Models\Division;
use App\Models\Event;
use App\Models\ScoringMatrix;
use App\Models\Season;
use App\Models\Standing;
use App\Models\Tournament;
use App\Models\TournamentFormat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Entering a tournament by hand, and the list of systems it is chosen from.
 */
class TournamentFormTest extends TestCase
{
    use RefreshDatabase;

    private Season $season;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::create([
            'name'     => 'Prüferin',
            'email'    => 'pruefung@example.test',
            'password' => 'geheim',
        ]));

        $this->season = Season::create([
            'year'              => 2026,
            'standing_id'       => Standing::create([
                'discipline_id' => Discipline::create(['name' => 'Langes Schwert'])->id,
                'division_id'   => Division::create(['name' => 'offen'])->id,
            ])->id,
            'scoring_matrix_id' => ScoringMatrix::create(['name' => 'Test', 'matrix' => '[]'])->id,
        ]);

        $this->event = Event::create(['name' => 'Dresdner Fechtschul', 'start_date' => '2026-10-24']);
    }

    public function test_a_tournament_without_a_field_size_is_refused_at_the_field(): void
    {
        // It used to reach the database as null and come back as an integrity constraint
        // violation - four times in a row on production before anybody saw why.
        Livewire::test(CreateTournament::class)
            ->fillForm([
                'event_id'  => $this->event->id,
                'season_id' => $this->season->id,
            ])
            ->call('create')
            ->assertHasFormErrors(['participant_count' => 'required']);

        $this->assertSame(0, Tournament::count());
    }

    public function test_the_system_is_chosen_from_the_list(): void
    {
        $system = TournamentFormat::where('name', 'Turnierbaum mit Vorrunde')->sole();

        Livewire::test(CreateTournament::class)
            ->fillForm([
                'event_id'             => $this->event->id,
                'season_id'            => $this->season->id,
                'participant_count'    => 25,
                'tournament_format_id' => $system->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('Turnierbaum mit Vorrunde', Tournament::sole()->format);
    }

    public function test_a_name_that_is_not_on_the_list_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Tournament::make()->format = 'Schweizer System';
    }

    public function test_the_systems_on_record_become_entries_of_the_list(): void
    {
        $migration = 'database/migrations/0027_01_01_000000_let_the_tournament_systems_be_a_list.php';
        Artisan::call('migrate:rollback', ['--path' => $migration]);

        $tournament = fn (?string $format) => DB::table('tournaments')->insertGetId([
            'public_id'         => substr(md5((string) $format . random_int(0, PHP_INT_MAX)), 0, 8),
            'season_id'         => $this->season->id,
            'event_id'          => $this->event->id,
            'participant_count' => 10,
            'format'            => $format,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        $bracket = $tournament('Turnierbaum');
        $pools = $tournament('Alle gegen alle');
        $none = $tournament(null);
        // A spelling nobody normalised. Kept, as an entry of its own.
        $odd = $tournament('Schweizer System');

        Artisan::call('migrate', ['--path' => $migration]);

        $this->assertSame('Turnierbaum', Tournament::find($bracket)->format);
        $this->assertSame('Alle gegen alle', Tournament::find($pools)->format);
        $this->assertNull(Tournament::find($none)->format);
        $this->assertSame('Schweizer System', Tournament::find($odd)->format);
        $this->assertSame(
            ['Alle gegen alle', 'Turnierbaum', 'Turnierbaum mit Vorrunde', 'Doppel-K.-o. mit Vorrunde', 'Schweizer System'],
            array_values(TournamentFormat::options()),
        );
    }

    public function test_a_decision_without_its_date_is_refused(): void
    {
        Livewire::test(CreateTournament::class)
            ->fillForm([
                'event_id'           => $this->event->id,
                'season_id'          => $this->season->id,
                'participant_count'  => 22,
                'registered_count'   => 25,
                'scored_on_decision' => 'Drei Absagen nach Meldeschluss, Präsidium hat zugestimmt.',
            ])
            ->call('create')
            ->assertHasFormErrors(['decided_on' => 'required_with']);

        $this->assertSame(0, Tournament::count());
    }

    public function test_a_decision_with_its_date_is_saved(): void
    {
        Livewire::test(CreateTournament::class)
            ->fillForm([
                'event_id'           => $this->event->id,
                'season_id'          => $this->season->id,
                'participant_count'  => 22,
                'registered_count'   => 25,
                'scored_on_decision' => 'Drei Absagen nach Meldeschluss, Präsidium hat zugestimmt.',
                'decided_on'         => '2026-11-01',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $tournament = Tournament::sole();

        $this->assertTrue($tournament->isScoredOnDecision());
        $this->assertSame('2026-11-01', $tournament->decided_on->toDateString());
        $this->assertSame(25, $tournament->registered_count);
    }

    public function test_a_decision_without_the_registrations_is_warned_about_not_refused(): void
    {
        Livewire::test(CreateTournament::class)
            ->fillForm([
                'event_id'           => $this->event->id,
                'season_id'          => $this->season->id,
                'participant_count'  => 22,
                'scored_on_decision' => 'Beschluss',
                'decided_on'         => '2026-11-01',
            ])
            ->assertSee('Der Beleg fehlt')
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(1, Tournament::count());
    }

    public function test_fewer_registrations_than_fencers_are_refused(): void
    {
        Livewire::test(CreateTournament::class)
            ->fillForm([
                'event_id'          => $this->event->id,
                'season_id'         => $this->season->id,
                'participant_count' => 22,
                'registered_count'  => 20,
            ])
            ->call('create')
            ->assertHasFormErrors(['registered_count']);
    }
}
