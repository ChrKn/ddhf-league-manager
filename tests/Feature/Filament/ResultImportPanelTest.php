<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\ResultImports\Pages\CreateResultImport;
use App\Filament\Resources\ResultImports\Pages\EditResultImport;
use App\Filament\Resources\ResultImports\Pages\ListResultImports;
use App\Filament\Resources\ResultImports\Pages\ReviewResultImport;
use App\Import\ImportRunner;
use App\Import\ImportStatus;
use App\Models\Discipline;
use App\Models\Division;
use App\Models\Event;
use App\Models\Fencer;
use App\Models\Result;
use App\Models\ResultImport;
use App\Models\ResultImportSheet;
use App\Models\ScoringMatrix;
use App\Models\Season;
use App\Models\Standing;
use App\Models\Tournament;
use App\Models\User;
use App\Standings\ScoringMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The import from the panel, through the pages rather than past them.
 *
 * The review page is a table of its own rather than a relation manager, because the rows hang off
 * the import through the sheets. These go through Livewire so that the wiring is what is tested -
 * the matching underneath has its own tests and does not need repeating here.
 */
class ResultImportPanelTest extends TestCase
{
    use RefreshDatabase;

    private Season $season;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->actingAs(User::create([
            'name'     => 'Prüferin',
            'email'    => 'pruefung@example.test',
            'password' => 'geheim',
        ]));

        $this->event = Event::create(['name' => 'Musterturnier', 'start_date' => '2026-05-09']);

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

    private function import(bool $mapped = true, string $fixture = 'vorlage-ohne-beispiel'): ResultImport
    {
        $import = ResultImport::create([
            'status'           => ImportStatus::Draft,
            'format'           => 'ddhf-vorlage',
            'remember_aliases' => false,
            'event_id'         => $this->event->id,
        ]);

        $path = "ergebnisimport/{$fixture}.xlsx";
        Storage::disk('local')->put($path, file_get_contents(__DIR__ . "/../../fixtures/import/{$fixture}.xlsx"));

        ResultImportSheet::create([
            'result_import_id' => $import->id,
            'original_name'    => "{$fixture}.xlsx",
            'stored_path'      => $path,
            'season_id'        => $mapped ? $this->season->id : null,
        ]);

        return $import->refresh();
    }

    private function planned(string $fixture = 'vorlage-ohne-beispiel'): ResultImport
    {
        $import = $this->import(fixture: $fixture);
        (new ImportRunner())->plan($import);

        return $import->refresh();
    }

    private function upload(string $fixture, string $as): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $as,
            file_get_contents(__DIR__ . "/../../fixtures/import/{$fixture}.xlsx"),
        );
    }

    public function test_uploading_reads_the_files_and_keeps_the_names_they_came_under(): void
    {
        Livewire::test(CreateResultImport::class)
            ->fillForm([
                'event_id' => $this->event->id,
                'format'  => 'ddhf-vorlage',
                'uploads' => [$this->upload('vorlage-ohne-beispiel', 'Musterturnier Langschwert.xlsx')],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $sheet = ResultImportSheet::sole();

        // The stored file is named by the system; this is the only thing left that ties a row
        // back to a file a person recognises.
        $this->assertSame('Musterturnier Langschwert.xlsx', $sheet->original_name);
        $this->assertNotSame('Musterturnier Langschwert.xlsx', basename($sheet->stored_path));

        // Read on upload rather than at the review step, so the mapping page can already say what
        // the file claims about itself.
        $this->assertSame(2, $sheet->participants);
        $this->assertSame('Turnierbaum', $sheet->format);
    }

    public function test_a_file_that_is_not_the_template_is_refused_at_the_upload(): void
    {
        Livewire::test(CreateResultImport::class)
            ->fillForm([
                'event_id' => $this->event->id,
                'format'  => 'ddhf-vorlage',
                'uploads' => [$this->upload('falsche-kopfzeile', 'Irgendwas.xlsx')],
            ])
            ->call('create')
            ->assertHasFormErrors(['uploads']);

        // Nothing created, and nothing left lying in the store either.
        $this->assertSame(0, ResultImport::count());
        $this->assertSame([], Storage::disk('local')->allFiles('ergebnisimport'));
    }

    public function test_the_list_shows_an_import(): void
    {
        $import = $this->import();

        Livewire::test(ListResultImports::class)
            ->assertCanSeeTableRecords([$import]);
    }

    public function test_the_mapping_page_matches_the_files(): void
    {
        $import = $this->import();

        Livewire::test(EditResultImport::class, ['record' => $import->getKey()])
            ->callAction('plan');

        $this->assertSame(2, $import->rows()->count());
        $this->assertSame(ImportStatus::Review, $import->refresh()->status);
    }

    public function test_a_file_without_a_standing_is_not_matched(): void
    {
        $import = $this->import(mapped: false);

        // The standing is a required field, so the save that precedes the matching stops on it
        // and the complaint lands at the field rather than in a notification. The runner refuses
        // the same thing again underneath - see PanelImportTest.
        Livewire::test(EditResultImport::class, ['record' => $import->getKey()])
            ->callAction('plan')
            ->assertHasErrors();

        $this->assertSame(0, $import->rows()->count());
        $this->assertSame(ImportStatus::Draft, $import->refresh()->status);
    }

    public function test_the_review_page_lists_the_open_rows(): void
    {
        $import = $this->planned();

        Livewire::test(ReviewResultImport::class, ['record' => $import->getKey()])
            ->assertCanSeeTableRecords($import->rows()->get());
    }

    public function test_a_selection_can_be_decided_at_once(): void
    {
        $import = $this->planned();

        Livewire::test(ReviewResultImport::class, ['record' => $import->getKey()])
            ->callTableBulkAction('bulkCreate', $import->rows()->get());

        $this->assertSame(0, $import->openRows());
        $this->assertSame(['create', 'create'], $import->rows()->pluck('action')->all());
    }

    public function test_using_the_suggestion_is_refused_where_there_is_none(): void
    {
        $import = $this->planned();

        // Nothing in this fixture exists in the database, so no row has a fencer to use. Letting
        // it through would fail at the write, naming a row nobody would remember selecting.
        Livewire::test(ReviewResultImport::class, ['record' => $import->getKey()])
            ->callTableBulkAction('bulkUse', $import->rows()->get())
            ->assertNotified();

        // Left as the matcher filled them in, rather than moved to a state that cannot be written.
        $this->assertSame(['create', 'create'], $import->rows()->pluck('action')->all());
    }

    public function test_the_row_is_edited_in_the_table_rather_than_in_a_modal(): void
    {
        $import = $this->planned();

        Livewire::test(ReviewResultImport::class, ['record' => $import->getKey()])
            ->assertCanRenderTableColumn('fencer_id')
            ->assertCanRenderTableColumn('group_id')
            ->assertCanRenderTableColumn('action');
    }

    public function test_asking_for_a_new_fencer_lets_go_of_the_assigned_one(): void
    {
        $import = $this->planned();
        $fencer = Fencer::create(['first_name' => 'Anna', 'last_name' => 'Beispiel', 'is_active' => true]);
        $import->rows()->update(['fencer_id' => $fencer->id, 'action' => 'use']);

        Livewire::test(ReviewResultImport::class, ['record' => $import->getKey()])
            ->callTableBulkAction('bulkCreate', $import->rows()->get());

        // Whether a fencer is created or reused is decided by whether one is assigned, not by the
        // word in the action column. Leaving the assignment in place would quietly reuse it, and
        // the row would say the opposite of what it did.
        $this->assertSame(['create', 'create'], $import->rows()->pluck('action')->all());
        $this->assertSame([null, null], $import->rows()->pluck('fencer_id')->all());
    }

    public function test_choosing_a_fencer_in_the_table_switches_the_row_to_using_them(): void
    {
        $import = $this->planned();
        $fencer = Fencer::create(['first_name' => 'Anna', 'last_name' => 'Beispiel', 'is_active' => true]);
        $row = $import->rows()->where('name', 'Anna Beispiel')->first();

        $this->assertSame('create', $row->action);

        $column = Livewire::test(ReviewResultImport::class, ['record' => $import->getKey()])
            ->instance()
            ->getTable()
            ->getColumn('fencer_id')
            ->record($row);

        $column->updateState($fencer->id);

        // Picking somebody and leaving the row on "neu anlegen" would create a second record for
        // a person who is on the screen, so the two move together.
        $this->assertSame($fencer->id, $row->refresh()->fencer_id);
        $this->assertSame('use', $row->action);
    }

    public function test_clearing_the_fencer_puts_the_row_back_on_creating_one(): void
    {
        $import = $this->planned();
        $fencer = Fencer::create(['first_name' => 'Anna', 'last_name' => 'Beispiel', 'is_active' => true]);
        $row = $import->rows()->where('name', 'Anna Beispiel')->first();
        $row->update(['fencer_id' => $fencer->id, 'action' => 'use']);

        $column = Livewire::test(ReviewResultImport::class, ['record' => $import->getKey()])
            ->instance()
            ->getTable()
            ->getColumn('fencer_id')
            ->record($row);

        $column->updateState(null);

        // "use" with nobody to use would fail at the write instead of here.
        $this->assertSame('create', $row->refresh()->action);
    }

    public function test_the_write_waits_until_a_row_the_matcher_could_not_read_is_answered(): void
    {
        // "Verletzung/Aufgabe" is not a placement, so nothing is filled in for it.
        $import = $this->planned('vorlage');

        Livewire::test(ReviewResultImport::class, ['record' => $import->getKey()])
            ->assertActionDisabled('apply');

        $import->rows()->update(['action' => 'skip']);

        Livewire::test(ReviewResultImport::class, ['record' => $import->getKey()])
            ->assertActionEnabled('apply');
    }

    public function test_applying_from_the_page_writes_the_results(): void
    {
        // Filled in by the matcher already; nothing had to be clicked.
        $import = $this->planned();

        $this->assertSame(0, $import->openRows());

        Livewire::test(ReviewResultImport::class, ['record' => $import->getKey()])
            ->callAction('apply');

        $this->assertSame(1, Tournament::count());
        $this->assertSame(2, Result::count());
        $this->assertSame(ImportStatus::Applied, $import->refresh()->status);
    }

    public function test_an_applied_import_offers_nothing_further(): void
    {
        $import = $this->planned();
        $import->rows()->update(['action' => 'create']);
        (new ImportRunner())->apply($import);

        Livewire::test(ReviewResultImport::class, ['record' => $import->refresh()->getKey()])
            ->assertActionHidden('apply')
            ->assertActionHidden('dryRun');
    }
}
