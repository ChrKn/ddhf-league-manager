<?php

namespace Tests\Feature\Import;

use App\Console\Commands\PrepareResultImport;
use App\Models\Discipline;
use App\Models\Division;
use App\Models\Event;
use App\Models\ScoringMatrix;
use App\Models\Season;
use App\Models\Standing;
use App\Models\Tournament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrepareResultImportTest extends TestCase
{
    use RefreshDatabase;

    private string $out;

    protected function setUp(): void
    {
        parent::setUp();

        $this->out = storage_path('app/testing-prepare.csv');
    }

    protected function tearDown(): void
    {
        foreach (glob(storage_path('app/testing-prepare*')) as $file) {
            unlink($file);
        }

        parent::tearDown();
    }

    private function fixture(string $name): string
    {
        return __DIR__ . "/../../fixtures/import/{$name}.xlsx";
    }

    private function tournament(int $participants): Tournament
    {
        $matrix = ScoringMatrix::firstOrCreate(['name' => 'Test'], ['matrix' => '[]']);
        $event = Event::firstOrCreate(['name' => 'Testevent'], ['start_date' => '2026-01-17']);
        $discipline = Discipline::firstOrCreate(['name' => 'Rapier']);

        $standing = Standing::create([
            'discipline_id' => $discipline->id,
            'division_id'   => Division::create(['name' => 'offen ' . Division::count()])->id,
        ]);

        return Tournament::create([
            'season_id' => Season::create([
                'year'              => 2026,
                'standing_id'       => $standing->id,
                'scoring_matrix_id' => $matrix->id,
            ])->id,
            'event_id'          => $event->id,
            'participant_count' => $participants,
        ]);
    }

    /** @return list<array<string, string>> */
    private function reviewFile(): array
    {
        $handle = fopen($this->out, 'r');
        $header = fgetcsv($handle, separator: ';', escape: '');
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);

        $rows = [];

        while (($values = fgetcsv($handle, separator: ';', escape: '')) !== false) {
            $rows[] = array_combine($header, array_pad($values, count($header), ''));
        }

        fclose($handle);

        return $rows;
    }

    public function test_several_files_end_up_in_one_review_file(): void
    {
        // Everything belonging to an event has to go through one run: someone entering two
        // tournaments appears in two files, and only within a run are they created once.
        $this->artisan('import:results-prepare', [
            'file' => [$this->fixture('vorlage'), $this->fixture('vorlage-ohne-beispiel')],
            '--tournament' => [
                'vorlage=' . $this->tournament(5)->public_id,
                'vorlage-ohne-beispiel=' . $this->tournament(2)->public_id,
            ],
            '--out' => $this->out,
        ])->assertSuccessful();

        $rows = $this->reviewFile();

        $this->assertCount(7, $rows);
        $this->assertSame(
            ['vorlage' => 5, 'vorlage-ohne-beispiel' => 2],
            array_count_values(array_column($rows, 'datei')),
        );
    }

    public function test_the_review_file_carries_the_tournament_system(): void
    {
        // Which convention the placements of a file follow depends on how it was fenced, so the
        // system has to reach the tournament rather than being read and dropped.
        $this->artisan('import:results-prepare', [
            'file'         => [$this->fixture('vorlage-ohne-beispiel')],
            '--tournament' => ['vorlage-ohne-beispiel=' . $this->tournament(2)->public_id],
            '--out'        => $this->out,
        ])->assertSuccessful();

        $this->assertSame(
            ['Turnierbaum', 'Turnierbaum'],
            array_column($this->reviewFile(), 'turniersystem'),
        );
    }

    public function test_a_file_naming_two_tournament_systems_stops_the_run(): void
    {
        $this->artisan('import:results-prepare', [
            'file'         => [$this->fixture('zwei-turniersysteme')],
            '--tournament' => ['zwei-turniersysteme=' . $this->tournament(2)->public_id],
            '--out'        => $this->out,
        ])->assertFailed();

        $this->assertFileDoesNotExist($this->out);
    }

    public function test_a_file_without_a_tournament_stops_the_run(): void
    {
        $this->artisan('import:results-prepare', [
            'file'  => [$this->fixture('vorlage')],
            '--out' => $this->out,
        ])->assertFailed();

        $this->assertFileDoesNotExist($this->out);
    }

    public function test_a_field_size_the_tournament_disagrees_with_stops_the_run(): void
    {
        // The points come from the participant count, so a tournament set to the wrong size
        // scores every placement against the wrong bracket.
        $this->artisan('import:results-prepare', [
            'file'         => [$this->fixture('vorlage')],
            '--tournament' => ['vorlage=' . $this->tournament(7)->public_id],
            '--out'        => $this->out,
        ])->assertFailed();

        $this->assertFileDoesNotExist($this->out);
    }

    public function test_a_file_disagreeing_with_itself_stops_the_run(): void
    {
        // The file states nine participants and lists two.
        $this->artisan('import:results-prepare', [
            'file'         => [$this->fixture('teilnehmerzahl-weicht-ab')],
            '--tournament' => ['teilnehmerzahl-weicht-ab=' . $this->tournament(9)->public_id],
            '--out'        => $this->out,
        ])->assertFailed();

        $this->assertFileDoesNotExist($this->out);
    }

    public function test_a_file_announced_as_partial_may_list_fewer_results(): void
    {
        // Some fields survive only in a record that dropped everyone it did not rank. The points
        // still hang on the real field size, so that number has to be right - only the row count
        // is let go, and only when the run says so.
        $this->artisan('import:results-prepare', [
            'file'         => [$this->fixture('teilnehmerzahl-weicht-ab')],
            '--tournament' => ['teilnehmerzahl-weicht-ab=' . $this->tournament(9)->public_id],
            '--partial'    => ['teilnehmerzahl-weicht-ab'],
            '--out'        => $this->out,
        ])->assertSuccessful();

        $this->assertCount(2, $this->reviewFile());
    }

    public function test_partial_still_insists_on_the_stated_field_size(): void
    {
        // The file says nine, the tournament says seven. Letting that through would score every
        // placement against the wrong bracket, which is what the check is for in the first place.
        $this->artisan('import:results-prepare', [
            'file'         => [$this->fixture('teilnehmerzahl-weicht-ab')],
            '--tournament' => ['teilnehmerzahl-weicht-ab=' . $this->tournament(7)->public_id],
            '--partial'    => ['teilnehmerzahl-weicht-ab'],
            '--out'        => $this->out,
        ])->assertFailed();

        $this->assertFileDoesNotExist($this->out);
    }

    public function test_partial_naming_a_file_that_is_not_there_stops_the_run(): void
    {
        // A typo in the file name would otherwise silently do nothing and let the row count check
        // fail with a message about a different problem.
        $this->artisan('import:results-prepare', [
            'file'         => [$this->fixture('vorlage')],
            '--tournament' => ['vorlage=' . $this->tournament(5)->public_id],
            '--partial'    => ['vorlarge'],
            '--out'        => $this->out,
        ])->assertFailed();

        $this->assertFileDoesNotExist($this->out);
    }

    public function test_two_files_of_the_same_name_stop_the_run(): void
    {
        // Their rows would share one tournament mapping and land in the same tournament.
        $copy = storage_path('app/testing-prepare-kopie/vorlage.xlsx');
        mkdir(dirname($copy), 0775, true);
        copy($this->fixture('vorlage'), $copy);

        $this->artisan('import:results-prepare', [
            'file'         => [$this->fixture('vorlage'), $copy],
            '--tournament' => ['vorlage=' . $this->tournament(5)->public_id],
            '--out'        => $this->out,
        ])->assertFailed();

        unlink($copy);
        rmdir(dirname($copy));
    }

    public function test_a_row_the_placement_cannot_be_read_from_is_left_for_a_decision(): void
    {
        $this->artisan('import:results-prepare', [
            'file'         => [$this->fixture('vorlage')],
            '--tournament' => ['vorlage=' . $this->tournament(5)->public_id],
            '--out'        => $this->out,
        ])->assertSuccessful();

        // The matcher fills in what it believes, here "create" throughout, because nothing in the
        // fixture exists in the database. The exception is the row whose placement is not a
        // placement: it cannot be written under any answer, so it is the one left blank.
        $rows = $this->reviewFile();
        $withdrawal = array_values(array_filter($rows, fn ($row) => $row['platz'] === 'Verletzung/Aufgabe'));
        $open = array_values(array_filter($rows, fn ($row) => $row['aktion'] === ''));

        $this->assertSame(['create', ''], array_values(array_unique(array_column($rows, 'aktion'))));
        $this->assertCount(1, $withdrawal);
        $this->assertCount(1, $open);
        $this->assertSame('Emil Ausfall', $withdrawal[0]['name_datei']);
        $this->assertSame('Emil Ausfall', $open[0]['name_datei']);
    }

    public function test_the_review_file_keeps_the_columns_the_apply_step_expects(): void
    {
        $this->artisan('import:results-prepare', [
            'file'         => [$this->fixture('vorlage')],
            '--tournament' => ['vorlage=' . $this->tournament(5)->public_id],
            '--out'        => $this->out,
        ])->assertSuccessful();

        $this->assertSame(PrepareResultImport::COLUMNS, array_keys($this->reviewFile()[0]));
    }
}
