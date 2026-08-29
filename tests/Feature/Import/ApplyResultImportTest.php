<?php

namespace Tests\Feature\Import;

use App\Console\Commands\PrepareResultImport;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplyResultImportTest extends TestCase
{
    use RefreshDatabase;

    private string $path;

    /** @var array<string, Tournament> */
    private array $tournaments = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = storage_path('app/testing-review.csv');

        $matrix = ScoringMatrix::create([
            'name'   => 'Test',
            'matrix' => json_encode([[
                'participants' => ['min' => 0],
                'points'       => [['place' => ['min' => 1, 'points' => 10]]],
            ]]),
        ]);
        $event = Event::create(['name' => 'Testevent', 'start_date' => '2026-01-17']);
        $discipline = Discipline::create(['name' => 'Rapier']);

        foreach (['offen', 'Damen+'] as $division) {
            $standing = Standing::create([
                'discipline_id' => $discipline->id,
                'division_id'   => Division::create(['name' => $division])->id,
            ]);
            $season = Season::create([
                'year'              => 2026,
                'standing_id'       => $standing->id,
                'scoring_matrix_id' => $matrix->id,
            ]);
            $this->tournaments[$division] = Tournament::create([
                'season_id'         => $season->id,
                'event_id'          => $event->id,
                'participant_count' => 5,
            ]);
        }
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }

        parent::tearDown();
    }

    /** @param list<array<string, string>> $rows */
    private function writeReviewFile(array $rows): void
    {
        $handle = fopen($this->path, 'w');
        fputcsv($handle, PrepareResultImport::COLUMNS, ';');

        foreach ($rows as $row) {
            fputcsv($handle, array_map(fn ($column) => $row[$column] ?? '', PrepareResultImport::COLUMNS), ';');
        }

        fclose($handle);
    }

    private function row(string $division, string $name, int $placement, string $aktion = 'create'): array
    {
        return [
            'aktion'     => $aktion,
            'turnier'    => $this->tournaments[$division]->public_id,
            'platz'      => (string) $placement,
            'name_datei' => $name,
        ];
    }

    public function test_the_tournament_records_how_it_was_fenced(): void
    {
        // It decides how the placements are to be read: in a bracket everyone who went out in the
        // same round shares the worst place of that round, in an all-against-all the placement is
        // the rank itself. Both occur in 2024, so the column has to say which.
        $this->writeReviewFile([
            ['turniersystem' => 'Turnierbaum'] + $this->row('offen', 'Petra Musterlein', 1),
            ['turniersystem' => 'Turnierbaum'] + $this->row('offen', 'Conny Probstett', 2),
            ['turniersystem' => 'Alle gegen alle'] + $this->row('Damen+', 'Anton Musterhain', 1),
        ]);

        $this->artisan('import:results-apply', ['file' => $this->path])->assertSuccessful();

        $this->assertSame('Turnierbaum', $this->tournaments['offen']->fresh()->format);
        $this->assertSame('Alle gegen alle', $this->tournaments['Damen+']->fresh()->format);
    }

    public function test_a_tournament_whose_sheet_names_no_system_keeps_none(): void
    {
        // The template column is free text and older sheets leave it empty. An empty value says
        // nothing, and guessing one would be worse than admitting nobody wrote it down.
        $this->writeReviewFile([$this->row('offen', 'Petra Musterlein', 1)]);

        $this->artisan('import:results-apply', ['file' => $this->path])->assertSuccessful();

        $this->assertNull($this->tournaments['offen']->fresh()->format);
    }

    public function test_a_fencer_entering_several_tournaments_is_created_once(): void
    {
        // Every row of a multi tournament event says "create" for the same person. Creating one
        // record per appearance would silently split them in two.
        $this->writeReviewFile([
            $this->row('offen', 'Petra Probenhain', 1),
            $this->row('Damen+', 'Petra Probenhain', 2),
        ]);

        $this->artisan('import:results-apply', ['file' => $this->path])->assertSuccessful();

        $this->assertSame(1, Fencer::where('last_name', 'Probenhain')->count());
        $this->assertSame(2, Result::count());
        $this->assertSame(2, Fencer::where('last_name', 'Probenhain')->first()->results()->count());
    }

    public function test_spelling_variants_of_one_name_still_produce_one_fencer(): void
    {
        $this->writeReviewFile([
            $this->row('offen', 'Heiner Musterloh', 1),
            $this->row('Damen+', 'HEINER MUSTERLOH', 2),
        ]);

        $this->artisan('import:results-apply', ['file' => $this->path])->assertSuccessful();

        $this->assertSame(1, Fencer::count());
    }

    public function test_a_dry_run_writes_nothing(): void
    {
        $this->writeReviewFile([$this->row('offen', 'Petra Probenhain', 1)]);

        $this->artisan('import:results-apply', ['file' => $this->path, '--dry-run' => true])
            ->assertSuccessful();

        $this->assertSame(0, Fencer::count());
        $this->assertSame(0, Result::count());
    }

    public function test_a_non_numeric_placement_stops_the_import(): void
    {
        $this->writeReviewFile([
            array_merge($this->row('offen', 'Anonymer Fechter', 1), ['platz' => 'Verletzung/Aufgabe']),
        ]);

        $this->artisan('import:results-apply', ['file' => $this->path])->assertFailed();

        $this->assertSame(0, Result::count());
    }

    public function test_rows_marked_skip_are_left_out(): void
    {
        $this->writeReviewFile([
            $this->row('offen', 'Petra Probenhain', 1),
            $this->row('offen', 'Johanna Musterhausen', 2, 'skip'),
        ]);

        $this->artisan('import:results-apply', ['file' => $this->path])->assertSuccessful();

        $this->assertSame(1, Result::count());
        $this->assertSame(1, Fencer::count());
    }

    public function test_create_inactive_marks_the_fencer_as_anonymized(): void
    {
        $this->writeReviewFile([
            $this->row('offen', 'Anonymer Fechter', 5, 'create_inactive'),
        ]);

        $this->artisan('import:results-apply', ['file' => $this->path])->assertSuccessful();

        $fencer = Fencer::first();
        $this->assertFalse((bool) $fencer->is_active);
    }

    public function test_two_unrecorded_fencers_do_not_become_one(): void
    {
        // The placeholder name matches every other unrecorded row. Reusing the record created for
        // the first one would pile placements of different people onto one fencer - which is what
        // happened to five results before this guard existed.
        $this->writeReviewFile([
            $this->row('offen', Fencer::ANONYMOUS_NAME, 3, 'create_inactive'),
            $this->row('Damen+', Fencer::ANONYMOUS_NAME, 4, 'create_inactive'),
        ]);

        $this->artisan('import:results-apply', ['file' => $this->path])->assertSuccessful();

        $this->assertSame(2, Fencer::count());
        $this->assertSame([1, 1], Fencer::withCount('results')->pluck('results_count')->all());
    }

    public function test_a_named_fencer_of_two_tournaments_is_still_created_once(): void
    {
        // The counterpart, so the guard above cannot be widened by accident.
        $this->writeReviewFile([
            $this->row('offen', 'Magret Test', 1),
            $this->row('Damen+', 'Magret Test', 2),
        ]);

        $this->artisan('import:results-apply', ['file' => $this->path])->assertSuccessful();

        $this->assertSame(1, Fencer::count());
        $this->assertSame(2, Fencer::first()->results()->count());
    }

    public function test_the_result_records_the_club_and_its_federation(): void
    {
        // The standing reads membership off the result, so the club has to land there and not
        // only at the person - otherwise a later club change would rewrite the past.
        $ddhf = Federation::create([
            'name'         => 'Deutscher Dachverband für historisches Fechten e. V.',
            'abbreviation' => Federation::OWN,
            'is_active'    => true,
        ]);
        $ochs = Group::create(['name' => 'Ochs', 'is_active' => true]);

        $ochs->federations()->attach($ddhf);

        $this->writeReviewFile([
            $this->row('offen', 'Petra Muster', 1) + [
                'verein_datei' => 'Ochs',
                'verein_id'    => $ochs->public_id,
            ],
        ]);

        $this->artisan('import:results-apply', ['file' => $this->path])->assertSuccessful();

        $result = Result::first();

        $this->assertSame($ochs->id, $result->group_id);
        $this->assertSame($ddhf->id, $result->federation_id);
        $this->assertSame('Ochs', $result->fencer_group_name);
    }

    public function test_a_club_the_file_does_not_mark_as_a_member_leaves_the_federation_empty(): void
    {
        // A club created from the file gets no federation, and the result inherits that. Not a
        // gap to be filled later: the file said the fencer is not one of ours.
        $this->writeReviewFile([
            $this->row('offen', 'Petra Muster', 1) + ['verein_datei' => 'Sprezzatura'],
        ]);

        $this->artisan('import:results-apply', ['file' => $this->path])->assertSuccessful();

        $result = Result::first();

        $this->assertSame(Group::where('name', 'Sprezzatura')->value('id'), $result->group_id);
        $this->assertNull($result->federation_id);
    }

    public function test_a_row_without_a_club_leaves_both_empty(): void
    {
        $this->writeReviewFile([$this->row('offen', 'Petra Muster', 1)]);

        $this->artisan('import:results-apply', ['file' => $this->path])->assertSuccessful();

        $result = Result::first();

        $this->assertNull($result->group_id);
        $this->assertNull($result->federation_id);
    }
}
