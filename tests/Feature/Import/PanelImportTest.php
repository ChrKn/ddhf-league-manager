<?php

namespace Tests\Feature\Import;

use App\Import\Formats\DdhfTemplate;
use App\Import\ImportFiles;
use App\Import\ImportRunner;
use App\Import\ImportStatus;
use App\Import\SheetIntake;
use App\Models\Discipline;
use App\Models\Division;
use App\Models\Event;
use App\Models\Fencer;
use App\Import\MatchConfidence;
use App\Models\Group;
use App\Models\Result;
use App\Models\ResultImport;
use App\Models\ResultImportRow;
use App\Models\ResultImportSheet;
use App\Models\ScoringMatrix;
use App\Models\Season;
use App\Models\Standing;
use App\Models\Tournament;
use App\Models\TournamentFormat;
use App\Standings\ScoringMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The import as it runs from the panel: files, a mapping, a review, and only then a write.
 *
 * The difference to the console is not the matching, which is the same code, but what happens
 * around it. The tournament is created by the import rather than chosen, so the guards against
 * running the same thing twice sit on the pairing of standing and event, and a review somebody
 * puts down has to survive being picked up.
 */
class PanelImportTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->event = Event::create(['name' => 'Musterturnier', 'start_date' => '2026-05-09']);
    }

    private function season(string $division = 'offen'): Season
    {
        return Season::create([
            'year'              => 2026,
            'standing_id'       => Standing::create([
                'discipline_id' => Discipline::firstOrCreate(['name' => 'Langes Schwert'])->id,
                'division_id'   => Division::create(['name' => $division])->id,
            ])->id,
            'scoring_matrix_id' => ScoringMatrix::firstOrCreate(
                ['name' => 'Test'],
                ['matrix' => json_encode([['participants' => ['min' => 0], 'points' => [['place' => ['min' => 1, 'points' => 1]]]]])],
            )->id,
            'scoring_mode' => ScoringMode::Standard,
        ]);
    }

    /**
     * An import with its files already up and mapped - what the panel has after the upload page
     * and the mapping page have been through.
     *
     * The files go in the way the upload page takes them in, so the field size is the one the
     * file proposes; the system is what the mapping form would have had chosen.
     *
     * @param  array<string, Season|null|array<string, Season>>  $files  fixture name => where it
     *                                                                    is headed, per sheet for a workbook of several
     */
    private function import(
        array $files,
        bool $partial = false,
        bool $withEvent = true,
        ?string $system = 'Turnierbaum',
    ): ResultImport {
        $import = ResultImport::create([
            'status'           => ImportStatus::Draft,
            'format'           => 'ddhf-vorlage',
            'remember_aliases' => false,
            // One event per import: what arrives together was fenced at the same one.
            'event_id'         => $withEvent ? $this->event->id : null,
        ]);

        foreach ($files as $fixture => $season) {
            $intake = SheetIntake::read([$this->upload($fixture) => "{$fixture}.xlsx"], DdhfTemplate::class);

            $this->assertTrue($intake->passes(), implode("\n", $intake->problems));

            foreach ($intake->sheets as $sheet) {
                ResultImportSheet::create($sheet + [
                    'result_import_id'     => $import->id,
                    'season_id'            => (is_array($season) ? $season[$sheet['worksheet']] : $season)?->id,
                    'tournament_format_id' => $system ? TournamentFormat::where('name', $system)->value('id') : null,
                    'partial'              => $partial,
                ]);
            }
        }

        return $import->refresh();
    }

    /** A fixture put where an upload lands, under a name of its own. */
    private function upload(string $fixture): string
    {
        $path = 'ergebnisimport/' . $fixture . '-' . bin2hex(random_bytes(4)) . '.xlsx';
        Storage::disk('local')->put($path, file_get_contents(__DIR__ . "/../../fixtures/import/{$fixture}.xlsx"));

        return $path;
    }

    /** Decide every row, so the import may be applied. */
    private function decideAll(ResultImport $import): void
    {
        foreach ($import->rows()->get() as $row) {
            // The withdrawal cannot be written - its placement is not a placement - so it goes
            // the way a reviewer would send it.
            $row->update(['action' => $row->placement === 'Verletzung/Aufgabe' ? 'skip' : 'create']);
        }
    }

    public function test_planning_writes_a_row_per_result(): void
    {
        $import = $this->import(['vorlage' => $this->season()]);

        $report = (new ImportRunner())->plan($import);

        $this->assertTrue($report->agrees(), implode("\n", $report->problems));
        $this->assertSame(5, $import->rows()->count());
        $this->assertSame(ImportStatus::Review, $import->refresh()->status);

        // Nothing in the fixture exists, so four rows are filled in as "create". The fifth says
        // "Verletzung/Aufgabe", which is not a placement and cannot be written under any answer -
        // that is the one the matcher forms no belief about, and it holds the import up.
        $this->assertSame(1, $import->openRows());
        $this->assertSame(
            ['create', 'create', 'create', 'create'],
            $import->rows()->whereNotNull('action')->pluck('action')->all(),
        );
    }

    public function test_the_file_proposes_how_large_the_field_was(): void
    {
        $sheet = $this->import(['vorlage' => $this->season()])->sheets()->first();

        $this->assertSame(5, $sheet->participants);
        $this->assertSame(5, $sheet->stated_participants);
        $this->assertSame(5, $sheet->row_count);
    }

    public function test_a_field_size_set_by_hand_survives_planning(): void
    {
        // The file says nine and lists two. Somebody looked, and two is right.
        $import = $this->import(['teilnehmerzahl-weicht-ab' => $this->season()]);
        $import->sheets()->update(['participants' => 2]);

        $report = (new ImportRunner())->plan($import);

        $this->assertTrue($report->agrees(), implode("\n", $report->problems));
        $this->assertSame(2, $import->sheets()->first()->participants);
        // Said, not held against it.
        $this->assertStringContainsString('übernommen wird 2', implode("\n", $report->notes));

        $this->decideAll($import);
        (new ImportRunner())->apply($import);

        $this->assertSame(2, Tournament::sole()->participant_count);
    }

    public function test_a_field_size_that_does_not_fit_the_rows_says_how_to_fix_it(): void
    {
        $import = $this->import(['vorlage' => $this->season()]);
        $import->sheets()->update(['participants' => 6]);

        $report = (new ImportRunner())->plan($import);

        $this->assertFalse($report->agrees());
        $this->assertStringContainsString('listet 5 Ergebnisse, festgelegt sind 6', implode("\n", $report->problems));
    }

    public function test_a_workbook_of_several_tournaments_gives_one_sheet_each(): void
    {
        $offen = $this->season();
        $damen = $this->season('Damen+');

        $import = $this->import(['zwei-blaetter' => ['Rapier Offen' => $offen, 'Rapier Damen+' => $damen]]);

        $this->assertSame(
            ['zwei-blaetter.xlsx · Rapier Offen', 'zwei-blaetter.xlsx · Rapier Damen+'],
            $import->sheets->pluck('original_name')->all(),
        );
        // One file under both.
        $this->assertCount(1, $import->sheets->pluck('stored_path')->unique());

        $report = (new ImportRunner())->plan($import);
        $this->assertTrue($report->agrees(), implode("\n", $report->problems));
        $this->decideAll($import);
        (new ImportRunner())->apply($import);

        $this->assertSame(2, Tournament::count());
        $this->assertSame(1, Tournament::where('season_id', $damen->id)->sole()->results()->count());
    }

    public function test_everything_missing_is_said_at_once(): void
    {
        $import = $this->import(
            ['vorlage' => null, 'vorlage-ohne-beispiel' => null],
            withEvent: false,
            system: null,
        );

        $problems = implode("\n", (new ImportRunner())->plan($import)->problems);

        $this->assertStringContainsString('keine Veranstaltung', $problems);
        $this->assertStringContainsString('"vorlage.xlsx": Rangliste, Turniersystem fehlt.', $problems);
        $this->assertStringContainsString('"vorlage-ohne-beispiel.xlsx": Rangliste, Turniersystem fehlt.', $problems);
    }

    public function test_the_system_chosen_in_the_mapping_goes_onto_the_tournament(): void
    {
        $import = $this->import(['vorlage-ohne-beispiel' => $this->season()], system: 'Doppel-K.-o. mit Vorrunde');

        (new ImportRunner())->plan($import);
        $this->decideAll($import);
        (new ImportRunner())->apply($import);

        $this->assertSame('Doppel-K.-o. mit Vorrunde', Tournament::sole()->format);
    }

    public function test_a_replaced_file_keeps_what_was_settled_for_it(): void
    {
        $season = $this->season();
        $import = $this->import(['teilnehmerzahl-weicht-ab' => $season], system: 'Alle gegen alle');
        $old = $import->sheets()->sole();
        $old->update(['participants' => 2]);

        (new ImportRunner())->plan($import);
        $this->assertSame(2, $import->rows()->count());

        $intake = ImportFiles::replace($import, $old->stored_path, [$this->upload('vorlage-ohne-beispiel') => 'korrigiert.xlsx']);

        $this->assertTrue($intake->passes(), implode("\n", $intake->problems));

        $sheet = $import->sheets()->sole();
        $this->assertSame('korrigiert.xlsx', $sheet->original_name);
        $this->assertSame($season->id, $sheet->season_id);
        $this->assertSame('Alle gegen alle', $sheet->tournamentFormat->name);
        // Entered by hand, so it stays.
        $this->assertSame(2, $sheet->participants);
        // The rows were matched against the old file.
        $this->assertSame(0, $import->rows()->count());
        $this->assertSame(ImportStatus::Draft, $import->refresh()->status);
        Storage::disk('local')->assertMissing($old->stored_path);
        Storage::disk('local')->assertExists($sheet->stored_path);
    }

    public function test_a_field_size_nobody_touched_follows_the_replaced_file(): void
    {
        $import = $this->import(['teilnehmerzahl-weicht-ab' => $this->season()]);
        $old = $import->sheets()->sole();

        ImportFiles::replace($import, $old->stored_path, [$this->upload('vorlage-ohne-beispiel') => 'neu.xlsx']);

        $this->assertSame(2, $import->sheets()->sole()->participants);
    }

    public function test_a_replacement_that_is_not_readable_changes_nothing(): void
    {
        $import = $this->import(['vorlage' => $this->season()]);
        $old = $import->sheets()->sole();
        $upload = $this->upload('falsche-kopfzeile');

        $intake = ImportFiles::replace($import, $old->stored_path, [$upload => 'falsch.xlsx']);

        $this->assertFalse($intake->passes());
        $this->assertStringContainsString('"falsch.xlsx": Die Kopfzeile entspricht nicht', implode("\n", $intake->problems));
        $this->assertSame($old->id, $import->sheets()->sole()->id);
        Storage::disk('local')->assertExists($old->stored_path);
        Storage::disk('local')->assertMissing($upload);
    }

    public function test_a_file_can_be_added_later(): void
    {
        $import = $this->import(['vorlage' => $this->season()]);

        $intake = ImportFiles::add($import, [$this->upload('vorlage-ohne-beispiel') => 'nachgereicht.xlsx']);

        $this->assertTrue($intake->passes());
        $this->assertSame(['vorlage.xlsx', 'nachgereicht.xlsx'], $import->sheets()->pluck('original_name')->all());
    }

    public function test_a_shared_file_goes_only_with_its_last_sheet(): void
    {
        $import = $this->import(['zwei-blaetter' => ['Rapier Offen' => null, 'Rapier Damen+' => null]]);
        [$first, $second] = $import->sheets->all();

        $first->delete();
        Storage::disk('local')->assertExists($second->stored_path);

        $second->delete();
        Storage::disk('local')->assertMissing($second->stored_path);
    }

    public function test_a_row_matching_on_both_sides_is_pre_filled(): void
    {
        $group = Group::create(['name' => 'Musterfechter', 'is_active' => true]);
        Fencer::create(['first_name' => 'Anna', 'last_name' => 'Beispiel', 'group_id' => $group->id, 'is_active' => true]);

        $import = $this->import(['vorlage' => $this->season()]);

        (new ImportRunner())->plan($import);

        $row = $import->rows()->where('name', 'Anna Beispiel')->first();

        $this->assertSame('use', $row->action);
        $this->assertSame(MatchConfidence::Exact, $row->fencer_confidence);

        // Found means "use", not found means "create". Only the unreadable placement stays open.
        $this->assertSame(1, $import->openRows());
        $this->assertSame(0, $import->uncertainRows());
    }

    public function test_a_likeness_is_filled_in_but_counted_as_one(): void
    {
        $group = Group::create(['name' => 'Musterfechter', 'is_active' => true]);
        // Near enough that the club settles it: within one club a surname is nearly unique.
        Fencer::create(['first_name' => 'Ana', 'last_name' => 'Beispiel', 'group_id' => $group->id, 'is_active' => true]);

        $import = $this->import(['vorlage' => $this->season()]);

        (new ImportRunner())->plan($import);

        $row = $import->rows()->where('name', 'Anna Beispiel')->first();

        // Filled in, because reviewing a filled column is the point. Counted, because accepting a
        // whole field at once has to be something somebody did knowingly.
        $this->assertSame('use', $row->action);
        $this->assertSame(MatchConfidence::Likely, $row->fencer_confidence);
        $this->assertSame(1, $import->uncertainRows());
    }

    public function test_a_match_too_weak_to_act_on_is_left_open(): void
    {
        // Similar at 0.79, which is below the 0.85 a name on its own has to reach.
        // MatchConfidence::Unsure means "the nearest thing there is", and acting on that unseen
        // hangs a result on somebody else - which is silent once written.
        Fencer::create(['first_name' => 'Anna', 'last_name' => 'Bespiegel', 'is_active' => true]);

        $import = $this->import(['vorlage' => $this->season()]);

        (new ImportRunner())->plan($import);

        $row = $import->rows()->where('name', 'Anna Beispiel')->first();

        $this->assertSame(MatchConfidence::Unsure, $row->fencer_confidence);
        $this->assertNull($row->action);
        $this->assertStringContainsString('ungefährer Treffer', $row->note);

        // The candidate is still on the row, so accepting it is one click rather than a search.
        $this->assertNotNull($row->fencer_id);
    }

    public function test_a_club_too_weak_to_act_on_is_left_open_as_well(): void
    {
        // The same danger on the other side: the result would record a club that was never there,
        // and the fencer would be created into it. Similar at 0.63, under the 0.75 that settles a
        // club - shown only because the file marks it as a member, so it has to exist somewhere.
        Group::create(['name' => 'Probierklingen Nord', 'is_active' => true]);

        $import = $this->import(['vorlage' => $this->season()]);

        (new ImportRunner())->plan($import);

        $row = $import->rows()->where('club', 'Probeklingen')->first();

        $this->assertSame(MatchConfidence::Unsure, $row->group_confidence);
        $this->assertNull($row->action);
    }

    public function test_applying_builds_the_tournament_out_of_the_file(): void
    {
        $season = $this->season();
        $import = $this->import(['vorlage-ohne-beispiel' => $season]);

        (new ImportRunner())->plan($import);
        $this->decideAll($import);

        $summary = (new ImportRunner())->apply($import);

        $tournament = Tournament::sole();

        $this->assertSame($season->id, $tournament->season_id);
        $this->assertSame($this->event->id, $tournament->event_id);
        // Both taken from the file. They are exactly what somebody would otherwise copy across,
        // and the field size is what every point in the standing is computed against.
        $this->assertSame(2, $tournament->participant_count);
        $this->assertSame('Turnierbaum', $tournament->format);

        $this->assertSame(2, $summary->results);
        $this->assertSame(2, Result::count());
        $this->assertSame(ImportStatus::Applied, $import->refresh()->status);
    }

    public function test_an_applied_row_says_what_became_of_it(): void
    {
        $import = $this->import(['vorlage-ohne-beispiel' => $this->season()]);

        (new ImportRunner())->plan($import);
        $this->decideAll($import);
        (new ImportRunner())->apply($import);

        $this->assertSame(
            Result::pluck('id')->sort()->values()->all(),
            $import->rows()->whereNotNull('result_id')->pluck('result_id')->sort()->values()->all(),
        );
    }

    public function test_a_withdrawal_is_kept_out_rather_than_guessed_at(): void
    {
        $import = $this->import(['vorlage' => $this->season()]);

        (new ImportRunner())->plan($import);
        $this->decideAll($import);
        (new ImportRunner())->apply($import);

        // Four of five rows: "Verletzung/Aufgabe" is not a placement and was sent to skip.
        $this->assertSame(4, Result::count());
        $this->assertSame(0, Fencer::where('last_name', 'Ausfall')->count());
    }

    public function test_a_tournament_already_there_stops_the_plan(): void
    {
        $season = $this->season();

        Tournament::create([
            'season_id'         => $season->id,
            'event_id'          => $this->event->id,
            'participant_count' => 5,
        ]);

        $report = (new ImportRunner())->plan($this->import(['vorlage' => $season]));

        // The guard the created tournament makes necessary: a second run would not double a
        // result, it would build a second tournament with a full field.
        $this->assertFalse($report->agrees());
        $this->assertStringContainsString('bereits ein Turnier', implode("\n", $report->problems));
    }

    public function test_two_files_headed_for_the_same_standing_stop_the_plan(): void
    {
        $season = $this->season();

        $report = (new ImportRunner())->plan($this->import([
            'vorlage'               => $season,
            'vorlage-ohne-beispiel' => $season,
        ]));

        $this->assertFalse($report->agrees());
        $this->assertStringContainsString('dieselbe Rangliste', implode("\n", $report->problems));
        $this->assertSame(0, ResultImportRow::count());
    }

    public function test_a_file_without_a_standing_stops_the_plan(): void
    {
        $report = (new ImportRunner())->plan($this->import(['vorlage' => null]));

        $this->assertFalse($report->agrees());
        $this->assertSame(0, ResultImportRow::count());
    }

    public function test_an_import_without_an_event_stops_the_plan(): void
    {
        $report = (new ImportRunner())->plan($this->import(['vorlage' => $this->season()], withEvent: false));

        $this->assertFalse($report->agrees());
        $this->assertStringContainsString('keine Veranstaltung', implode("\n", $report->problems));
        $this->assertSame(0, ResultImportRow::count());
    }

    public function test_a_file_that_disagrees_with_itself_stops_the_plan(): void
    {
        // The file states nine participants and lists two.
        $report = (new ImportRunner())->plan($this->import(['teilnehmerzahl-weicht-ab' => $this->season()]));

        $this->assertFalse($report->agrees());
        $this->assertSame(0, ResultImportRow::count());
    }

    public function test_a_file_announced_as_partial_may_list_fewer(): void
    {
        $report = (new ImportRunner())->plan($this->import(['teilnehmerzahl-weicht-ab' => $this->season()], partial: true));

        $this->assertTrue($report->agrees(), implode("\n", $report->problems));
        $this->assertSame(2, ResultImportRow::count());
        $this->assertNotSame([], $report->notes);
    }

    public function test_an_abandoned_import_leaves_no_tournament(): void
    {
        $import = $this->import(['vorlage' => $this->season()]);

        (new ImportRunner())->plan($import);

        $path = $import->sheets()->first()->stored_path;
        $import->delete();

        // The reason the tournament is created at the write and not at the upload: an empty one
        // left behind reads later as a real tournament whose results nobody entered.
        $this->assertSame(0, Tournament::count());
        $this->assertSame(0, ResultImportRow::count());
        $this->assertSame(0, ResultImportSheet::count());

        // And the file goes too. The cascade that removes the sheets fires no model event, so
        // without a hook on the import a sheet full of names would stay in the store with
        // nothing left pointing at it.
        Storage::disk('local')->assertMissing($path);
    }

    public function test_an_import_is_not_written_twice(): void
    {
        $import = $this->import(['vorlage-ohne-beispiel' => $this->season()]);

        (new ImportRunner())->plan($import);
        $this->decideAll($import);
        (new ImportRunner())->apply($import);

        $this->expectExceptionMessage('bereits abgeschlossen');

        (new ImportRunner())->apply($import->refresh());
    }

    public function test_a_row_left_open_stops_the_write(): void
    {
        // The withdrawal in this fixture: its placement is not a placement, so the matcher fills
        // nothing in and the write has to refuse until somebody says what to do with it.
        $import = $this->import(['vorlage' => $this->season()]);

        (new ImportRunner())->plan($import);
        $import->rows()->where('placement', '!=', 'Verletzung/Aufgabe')->update(['action' => 'create']);

        $this->expectExceptionMessage('aktion');

        (new ImportRunner())->apply($import);
    }

    public function test_a_probe_run_writes_nothing(): void
    {
        $import = $this->import(['vorlage-ohne-beispiel' => $this->season()]);

        (new ImportRunner())->plan($import);
        $this->decideAll($import);

        $summary = (new ImportRunner())->apply($import, dryRun: true);

        $this->assertSame(2, $summary->results);
        $this->assertTrue($summary->dryRun);
        $this->assertSame(0, Result::count());
        $this->assertSame(0, Tournament::count());
        $this->assertSame(ImportStatus::Review, $import->refresh()->status);
    }

    public function test_someone_in_two_files_is_created_once(): void
    {
        // The reason all files of an event belong in one import. Anna Beispiel is in both.
        $import = $this->import([
            'vorlage'               => $this->season('offen'),
            'vorlage-ohne-beispiel' => $this->season('Damen+'),
        ]);

        (new ImportRunner())->plan($import);
        $this->decideAll($import);
        (new ImportRunner())->apply($import);

        $this->assertSame(1, Fencer::where('last_name', 'Beispiel')->count());
        $this->assertSame(2, Tournament::count());
    }

    public function test_a_second_import_does_not_pile_a_second_result_onto_the_same_person(): void
    {
        $season = $this->season();
        $import = $this->import(['vorlage-ohne-beispiel' => $season]);

        (new ImportRunner())->plan($import);
        $this->decideAll($import);
        (new ImportRunner())->apply($import);

        // A tournament of that standing now exists, so the plan refuses before anything is
        // matched. This is the backstop, reached only by pointing a second import at a
        // tournament by hand.
        $tournament = Tournament::sole();
        $fencer = Fencer::where('last_name', 'Beispiel')->sole();

        $again = $this->import(['vorlage-ohne-beispiel' => $season]);
        $again->sheets()->update(['tournament_id' => $tournament->id]);

        (new ImportRunner())->plan($again->refresh());

        $row = $again->rows()->where('name', 'Anna Beispiel')->first();

        $this->assertSame('skip', $row->action);
        $this->assertStringContainsString('existiert bereits', $row->note);
        $this->assertSame(1, Result::where('fencer_id', $fencer->id)->count());
    }
}
