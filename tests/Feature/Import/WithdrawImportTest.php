<?php

namespace Tests\Feature\Import;

use App\Import\ImportRunner;
use App\Import\ImportStatus;
use App\Models\Discipline;
use App\Models\Division;
use App\Models\Event;
use App\Models\Federation;
use App\Models\Fencer;
use App\Models\Group;
use App\Models\Result;
use App\Models\ResultImport;
use App\Models\ResultImportSheet;
use App\Models\ScoringMatrix;
use App\Models\Season;
use App\Models\Standing;
use App\Models\Tournament;
use App\Standings\ScoringMode;
use App\Standings\SeasonRanking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The way back out of a mis-import.
 *
 * Before this, a wrongly applied import meant repairing the tables by hand - which is exactly the
 * human effort the whole system exists to remove. Everything needed was already written down; only
 * the way back was missing.
 *
 * The first test is the one that counts, because it asks after the consequence rather than the
 * bookkeeping: the standing is what it was before the import.
 */
class WithdrawImportTest extends TestCase
{
    use RefreshDatabase;

    private Season $season;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $ddhf = Federation::create([
            'name'         => 'Deutscher Dachverband für historisches Fechten e. V.',
            'abbreviation' => Federation::OWN,
            'is_active'    => true,
        ]);

        // The club from the sheet, already on record as a member - which is the ordinary case, and
        // the only one in which anything scores. A club the import creates gets no membership on
        // the strength of a spreadsheet cell, so its results count towards nothing.
        Group::create(['name' => 'Musterfechter', 'is_active' => true])
            ->federations()
            ->attach($ddhf);

        $this->event = Event::create(['name' => 'Musterturnier', 'start_date' => '2026-05-09']);

        $this->season = Season::create([
            'year'              => 2026,
            'standing_id'       => Standing::create([
                'discipline_id' => Discipline::create(['name' => 'Langes Schwert'])->id,
                'division_id'   => Division::create(['name' => 'offen'])->id,
            ])->id,
            'scoring_matrix_id' => ScoringMatrix::create([
                'name'   => 'Test',
                'matrix' => json_encode([[
                    'participants' => ['min' => 0],
                    'points'       => [['place' => ['min' => 1, 'points' => 10]]],
                ]]),
            ])->id,
            'scoring_mode' => ScoringMode::Standard,
        ]);
    }

    /** An import that has been through planning and applying, as the panel would leave it. */
    private function applied(): ResultImport
    {
        $import = ResultImport::create([
            'status'           => ImportStatus::Draft,
            'format'           => 'ddhf-vorlage',
            'remember_aliases' => false,
            'event_id'         => $this->event->id,
        ]);

        $path = 'ergebnisimport/vorlage-ohne-beispiel.xlsx';
        Storage::disk('local')->put(
            $path,
            file_get_contents(__DIR__ . '/../../fixtures/import/vorlage-ohne-beispiel.xlsx'),
        );

        ResultImportSheet::create([
            'result_import_id' => $import->id,
            'original_name'    => 'vorlage-ohne-beispiel.xlsx',
            'stored_path'      => $path,
            'season_id'        => $this->season->id,
        ]);

        $import->refresh();

        $runner = new ImportRunner();
        $runner->plan($import);
        $runner->apply($import->refresh());

        return $import->refresh();
    }

    /** @return list<string> the standing as a reader sees it: name, rank and points */
    private function standing(): array
    {
        return collect(SeasonRanking::for($this->season->fresh()->load(SeasonRanking::relations()))['rows'])
            ->map(fn (array $row) => "{$row['rank']} {$row['name']} {$row['points']}")
            ->all();
    }

    public function test_the_standing_is_what_it_was_before(): void
    {
        $this->assertSame([], $this->standing());

        $import = $this->applied();

        $this->assertNotSame([], $this->standing(), 'Der Import hat nichts geschrieben.');

        (new ImportRunner())->withdraw($import);

        // Not "two results were deleted" - the question is whether the tables a reader looks at
        // are back where they started.
        $this->assertSame([], $this->standing());
    }

    public function test_the_tournament_goes_with_its_last_result(): void
    {
        $import = $this->applied();

        $this->assertSame(1, Tournament::count());

        (new ImportRunner())->withdraw($import);

        // It only ever existed for this import, so leaving it would leave a tournament whose
        // results nobody entered - which reads as a real one.
        $this->assertSame(0, Tournament::count());
        $this->assertSame(0, Result::count());
    }

    public function test_a_tournament_with_somebody_elses_result_stays(): void
    {
        $import = $this->applied();
        $tournament = Tournament::sole();

        $stranger = Fencer::create(['first_name' => 'Von', 'last_name' => 'Hand', 'is_active' => true]);

        Result::create([
            'tournament_id' => $tournament->id,
            'fencer_id'     => $stranger->id,
            'placement'     => '9',
        ]);

        (new ImportRunner())->withdraw($import);

        // The import's results go; the tournament is no longer the import's to remove.
        $this->assertNotNull($tournament->fresh());
        $this->assertSame(1, Result::count());
        $this->assertSame($stranger->id, Result::sole()->fencer_id);
    }

    public function test_a_result_edited_afterwards_stops_it(): void
    {
        $import = $this->applied();

        $this->travel(1)->minutes();
        Result::first()->update(['placement' => '3']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/bearbeitet/');

        (new ImportRunner())->withdraw($import->fresh());
    }

    public function test_nothing_is_lost_when_it_refuses(): void
    {
        $import = $this->applied();
        $results = Result::count();

        $this->travel(1)->minutes();
        $corrected = Result::first();
        $corrected->update(['placement' => '3']);

        try {
            (new ImportRunner())->withdraw($import->fresh());
        } catch (\RuntimeException) {
            // expected
        }

        // A refusal has to leave everything exactly as it was, or it is worse than no refusal:
        // half a withdrawal is the state nobody can reason about.
        $this->assertSame(ImportStatus::Applied, $import->fresh()->status);
        $this->assertNotNull($import->fresh()->applied_at);
        $this->assertSame($results, Result::count());
        $this->assertSame('3', $corrected->fresh()->placement, 'Die Bearbeitung von Hand steht noch.');
        $this->assertSame(1, Tournament::count());
    }

    public function test_a_dry_run_changes_nothing(): void
    {
        $import = $this->applied();
        $before = $this->standing();

        $summary = (new ImportRunner())->withdraw($import, dryRun: true);

        $this->assertTrue($summary->dryRun);
        $this->assertGreaterThan(0, $summary->results);
        $this->assertSame($before, $this->standing());
        $this->assertSame(ImportStatus::Applied, $import->fresh()->status);
    }

    public function test_it_can_be_applied_again_afterwards(): void
    {
        $import = $this->applied();
        $before = $this->standing();

        (new ImportRunner())->withdraw($import);

        // The whole point: correct the rows and run it again. The guard against a second
        // tournament for the same season and event lets go, because the first one is gone.
        (new ImportRunner())->apply($import->fresh());

        $this->assertSame($before, $this->standing());
    }

    public function test_the_import_goes_back_into_review(): void
    {
        $import = $this->applied();

        (new ImportRunner())->withdraw($import);

        $import->refresh();

        $this->assertSame(ImportStatus::Review, $import->status);
        $this->assertNull($import->applied_at);
        $this->assertSame(0, $import->rows()->whereNotNull('result_id')->count());
        $this->assertNull($import->sheets()->sole()->tournament_id);
    }

    public function test_an_import_that_was_never_applied_cannot_be_taken_back(): void
    {
        $import = ResultImport::create([
            'status'   => ImportStatus::Review,
            'format'   => 'ddhf-vorlage',
            'event_id' => $this->event->id,
        ]);

        $this->expectException(\RuntimeException::class);

        (new ImportRunner())->withdraw($import);
    }

    public function test_the_fencers_it_created_stay_and_are_named(): void
    {
        $import = $this->applied();

        $created = Fencer::count();
        $this->assertGreaterThan(0, $created);

        $summary = (new ImportRunner())->withdraw($import);

        // They stay: a later import may be using them by now, and one without results bothers
        // nobody. What the summary owes is telling somebody which they are.
        $this->assertSame($created, Fencer::count());
        $this->assertCount($created, $summary->orphanedFencers);
    }

    public function test_a_fencer_with_another_result_is_not_reported_as_spare(): void
    {
        $import = $this->applied();
        $fencer = Fencer::first();

        $other = Tournament::create([
            'season_id'         => $this->season->id,
            'event_id'          => Event::create(['name' => 'Anderes', 'start_date' => '2026-06-01'])->id,
            'participant_count' => 10,
        ]);

        Result::create(['tournament_id' => $other->id, 'fencer_id' => $fencer->id, 'placement' => '2']);

        $summary = (new ImportRunner())->withdraw($import);

        $this->assertStringNotContainsString($fencer->public_id, implode(' ', $summary->orphanedFencers));
    }
}
