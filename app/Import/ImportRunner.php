<?php

namespace App\Import;

use App\Import\Formats\SheetFormats;
use App\Models\Fencer;
use App\Models\Group;
use App\Models\Result;
use App\Models\ResultImport;
use App\Models\ResultImportRow;
use App\Models\ResultImportSheet;
use App\Models\Tournament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Drives a stored import: reads its files, matches them, and later writes them.
 *
 * The two halves are the same ones the console has always had, and they run the same code. What
 * this adds is that the middle - the matched rows and the decisions taken about them - survives
 * being put down and picked up again.
 *
 * The tournament is created here, at the moment of writing and inside the same transaction. Not
 * at upload: a review somebody abandons would otherwise leave an empty tournament behind, which
 * later reads as a real one whose results nobody entered.
 */
class ImportRunner
{
    /**
     * Match the files against the database and store a row per result.
     *
     * Returns what stands in the way. Rows are written only when nothing does.
     */
    public function plan(ResultImport $import): ConsistencyReport
    {
        $import->load(['event', 'sheets.tournament', 'sheets.season']);
        $this->tieSheetsToTheImport($import);

        if ($import->sheets->isEmpty()) {
            return new ConsistencyReport(['Es ist keine Datei hochgeladen.']);
        }

        if ($import->event_id === null) {
            return new ConsistencyReport(['Für diesen Import ist keine Veranstaltung gewählt.']);
        }

        $unmapped = $import->sheets->filter(fn (ResultImportSheet $sheet) => !$sheet->season_id);

        if ($unmapped->isNotEmpty()) {
            return new ConsistencyReport($unmapped
                ->map(fn (ResultImportSheet $sheet) => "\"{$sheet->original_name}\": keine Rangliste gewählt.")
                ->values()
                ->all());
        }

        try {
            $rows = ImportPlanner::read($this->paths($import), SheetFormats::byKey($import->format));
        } catch (RuntimeException $exception) {
            return new ConsistencyReport([$exception->getMessage()]);
        }

        $this->recordWhatTheFilesSay($import, $rows);

        $tournaments = [];
        $partial = [];
        $problems = [];

        foreach ($import->sheets as $sheet) {
            $tournaments[$sheet->original_name] = $sheet->tournament;

            if ($sheet->partial) {
                $partial[] = $sheet->original_name;
            }

            // The guard against running the same file twice. Because the importer creates the
            // tournament, a second run would not double a result - it would build a second
            // tournament with a full field, and nothing downstream would look wrong.
            if ($already = $sheet->tournamentAlreadyThere()) {
                $problems[] = sprintf(
                    '"%s": für diese Saison und diese Veranstaltung gibt es bereits ein Turnier (%s, %d Teilnehmer). '
                    . 'Ein Import legt nur neue Turniere an.',
                    $sheet->original_name,
                    $already->public_id,
                    $already->participant_count,
                );
            }
        }

        $problems = array_merge($problems, $this->collidingSheets($import));

        $consistency = ImportPlanner::check($rows, $tournaments, $partial);
        $problems = array_merge($problems, $consistency->problems);

        if ($problems !== []) {
            return new ConsistencyReport($problems, $consistency->notes);
        }

        $this->store($import, (new ImportPlanner())->plan($rows, $tournaments));

        return new ConsistencyReport([], $consistency->notes);
    }

    /**
     * Create the tournaments and write the results.
     *
     * @throws RuntimeException when a row is still undecided, or a guard has been tripped since
     *                          the plan was made
     * @throws \Throwable
     */
    public function apply(ResultImport $import, bool $dryRun = false): ImportSummary
    {
        if (!$import->status->isOpen()) {
            throw new RuntimeException('Dieser Import ist bereits abgeschlossen.');
        }

        $this->tieSheetsToTheImport($import);

        DB::beginTransaction();

        try {
            $rowModels = $import->rows()
                ->with(['fencer', 'group', 'sheet'])
                ->orderBy('result_import_sheet_id')
                ->orderBy('sheet_row')
                ->get();

            if ($rowModels->isEmpty()) {
                throw new RuntimeException('Es sind keine geprüften Zeilen da.');
            }

            // Both guards run again here rather than being trusted from the plan: the mapping may
            // have been changed, or a tournament entered by hand, in between.
            if (($colliding = $this->collidingSheets($import)) !== []) {
                throw new RuntimeException(implode("\n", $colliding));
            }

            foreach ($import->sheets as $sheet) {
                // Checked again rather than trusted from the plan: somebody may have entered the
                // tournament by hand in the meantime.
                if ($already = $sheet->tournamentAlreadyThere()) {
                    throw new RuntimeException(sprintf(
                        '"%s": inzwischen gibt es für diese Saison und Veranstaltung ein Turnier (%s).',
                        $sheet->original_name,
                        $already->public_id,
                    ));
                }

                $tournament = $this->tournamentFor($sheet);
                $sheet->update(['tournament_id' => $tournament->id]);
                // Set rather than left to load itself: the relation may already be loaded and
                // empty from the planning step, and a row would then name no tournament at all.
                $sheet->setRelation('tournament', $tournament);
            }

            // Read after the tournaments exist, so every row now names the one it belongs to.
            $rowModels->each(fn (ResultImportRow $row) => $row->setRelation(
                'sheet',
                $import->sheets->firstWhere('id', $row->result_import_sheet_id),
            ));

            $planRows = $rowModels->map(fn (ResultImportRow $row) => $row->toPlanRow())->all();

            if (($problems = ImportWriter::validate($planRows)) !== []) {
                throw new RuntimeException(implode("\n", $problems));
            }

            $summary = (new ImportWriter(rememberAliases: $import->remember_aliases))->write($planRows);

            foreach ($summary->resultIds as $index => $resultId) {
                $rowModels[$index]->update(['result_id' => $resultId]);
            }

            $import->update([
                'status'     => ImportStatus::Applied,
                'applied_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            DB::rollBack();

            throw $exception;
        }

        $dryRun ? DB::rollBack() : DB::commit();

        return new ImportSummary(
            results: $summary->results,
            clubs: $summary->clubs,
            fencers: $summary->fencers,
            aliases: $summary->aliases,
            skipped: $summary->skipped,
            duplicates: $summary->duplicates,
            dryRun: $dryRun,
            resultIds: $summary->resultIds,
            aliasConflicts: $summary->aliasConflicts,
        );
    }

    /**
     * Two files of one import headed for the same standing at the same event.
     *
     * The event is the import's, so this comes down to two files claiming the same standing.
     * tournamentAlreadyThere() only looks at what is in the database, so it cannot see this: both
     * files would pass, and applying would build two tournaments of the same standing at the same
     * event, each with a full field. Which is what a longsword file uploaded twice under two
     * names looks like.
     *
     * @return list<string>
     */
    private function collidingSheets(ResultImport $import): array
    {
        $problems = [];

        $collisions = $import->sheets
            ->groupBy(fn (ResultImportSheet $sheet) => (string) $sheet->season_id)
            ->filter(fn ($sheets) => $sheets->count() > 1);

        foreach ($collisions as $sheets) {
            $problems[] = 'Diese Dateien sollen in dieselbe Rangliste: '
                . $sheets->pluck('original_name')->implode(', ')
                . '. Der Import gehört zu einer Veranstaltung, es gäbe also zwei gleiche Turniere.';
        }

        return $problems;
    }

    /**
     * The tournament this file becomes.
     *
     * Its field size and system come from the file, which is the point of letting the import
     * create it: those two are exactly what somebody would otherwise copy across by hand, and
     * the field size is what every point in the standing is computed against.
     */
    private function tournamentFor(ResultImportSheet $sheet): Tournament
    {
        return Tournament::create([
            'season_id'         => $sheet->season_id,
            'event_id'          => $sheet->import->event_id,
            'participant_count' => $sheet->participants,
            'format'            => $sheet->format ?: null,
        ]);
    }

    /**
     * The sheets need their import back, because the event now lives there.
     *
     * Set rather than left to load itself: a sheet asking for its import would otherwise fetch a
     * second copy of the record we already have, once per sheet and once per question.
     */
    private function tieSheetsToTheImport(ResultImport $import): void
    {
        $import->sheets->each(fn (ResultImportSheet $sheet) => $sheet->setRelation('import', $import));
    }

    /** @return array<string, string> file name as sent => where it lies */
    private function paths(ResultImport $import): array
    {
        $paths = [];

        foreach ($import->sheets as $sheet) {
            $path = Storage::disk('local')->path($sheet->stored_path);

            if (!is_file($path)) {
                throw new RuntimeException("Die hochgeladene Datei ist nicht mehr da: {$sheet->original_name}");
            }

            $paths[$sheet->original_name] = $path;
        }

        return $paths;
    }

    /**
     * Field size and tournament system as the file states them, onto the sheet.
     *
     * The tournament is built from these later, so they are worth having where somebody can read
     * them before that happens.
     *
     * @param  list<array<string, string>>  $rows
     */
    private function recordWhatTheFilesSay(ResultImport $import, array $rows): void
    {
        foreach ($import->sheets as $sheet) {
            $ofSheet = array_values(array_filter($rows, fn ($row) => $row['sheet'] === $sheet->original_name));
            $first = $ofSheet[0] ?? null;

            $sheet->update([
                'participants' => $first ? (int) $first['participants'] : null,
                'format'       => $first && $first['system'] !== '' ? $first['system'] : null,
            ]);
        }
    }

    /**
     * Take an applied import back off the tables a standing is computed from.
     *
     * The way out of a mis-import. Everything needed is already written down - `applied_at`, the
     * `result_id` on each row, the `tournament_id` on each sheet - so this removes exactly what
     * this import put there and nothing that resembles it.
     *
     * Fencers and clubs stay. A later import may be using them by now, and one without results
     * bothers nobody; the summary names those that are left over so somebody can decide. Club
     * spellings the import remembered stay as well: that a spelling means that club is still true
     * afterwards.
     *
     * Afterwards the import is back in review, so the rows can be corrected and applied again -
     * which is the point of the whole thing.
     *
     * @throws \Throwable
     */
    public function withdraw(ResultImport $import, bool $dryRun = false): WithdrawalSummary
    {
        if ($import->status !== ImportStatus::Applied) {
            throw new RuntimeException('Nur ein übernommener Import lässt sich zurücknehmen.');
        }

        $resultIds = $import->rows()->whereNotNull('result_id')->pluck('result_id')->all();

        // Somebody has corrected a result by hand since. Taking it back would throw that work away
        // without asking. Second precision on both sides, so an edit within the same second as the
        // apply is not seen - which is not a case that happens outside a test.
        $touched = Result::whereIn('id', $resultIds)
            ->where('updated_at', '>', $import->applied_at)
            ->get();

        if ($touched->isNotEmpty()) {
            throw new RuntimeException(sprintf(
                "%d Ergebnisse wurden seit der Übernahme bearbeitet: %s.\n"
                . 'Zurücknehmen würde diese Arbeit verwerfen — bitte erst ansehen.',
                $touched->count(),
                $touched->take(5)->map(fn (Result $r) => $r->fencer?->display_name ?? "#{$r->id}")->implode(', '),
            ));
        }

        DB::beginTransaction();

        try {
            $summary = $this->undo($import, $resultIds);
        } catch (\Throwable $exception) {
            DB::rollBack();

            throw $exception;
        }

        $dryRun ? DB::rollBack() : DB::commit();

        return new WithdrawalSummary(
            results: $summary['results'],
            tournaments: $summary['tournaments'],
            orphanedFencers: $summary['fencers'],
            orphanedClubs: $summary['clubs'],
            dryRun: $dryRun,
        );
    }

    /**
     * @param  list<int>  $resultIds
     * @return array{results: int, tournaments: list<string>, fencers: list<string>, clubs: list<string>}
     */
    private function undo(ResultImport $import, array $resultIds): array
    {
        // Noted before the results go, because afterwards there is nothing left pointing at them.
        $fencerIds = Result::whereIn('id', $resultIds)->pluck('fencer_id')->filter()->unique();
        $clubIds = Result::whereIn('id', $resultIds)->pluck('group_id')->filter()->unique();

        $removed = Result::whereIn('id', $resultIds)->delete();

        $tournaments = [];

        foreach ($import->sheets as $sheet) {
            $tournament = $sheet->tournament_id ? Tournament::find($sheet->tournament_id) : null;

            // Only if nothing is left. A result somebody entered by hand means the tournament is
            // no longer this import's to remove.
            if ($tournament && $tournament->results()->count() === 0) {
                $tournaments[] = $tournament->public_id;
                $tournament->delete();
            }

            $sheet->update(['tournament_id' => null]);
        }

        $import->rows()->update(['result_id' => null]);

        $import->update([
            'status'     => ImportStatus::Review,
            'applied_at' => null,
        ]);

        return [
            'results'     => $removed,
            'tournaments' => $tournaments,
            'fencers'     => Fencer::whereIn('id', $fencerIds)
                ->doesntHave('results')
                ->get()
                ->map(fn (Fencer $f) => "{$f->display_name} ({$f->public_id})")
                ->all(),
            'clubs' => Group::whereIn('id', $clubIds)
                ->whereDoesntHave('fencers')
                ->whereNotIn('id', Result::whereNotNull('group_id')->distinct()->pluck('group_id'))
                ->get()
                ->map(fn (Group $g) => "{$g->name} ({$g->public_id})")
                ->all(),
        ];
    }

    private function store(ResultImport $import, ImportPlan $plan): void
    {
        DB::transaction(function () use ($import, $plan): void {
            $sheets = $import->sheets->keyBy('original_name');

            // A second planning run replaces the first. Decisions taken in between are lost,
            // which is why the panel asks before doing it.
            ResultImportRow::whereIn('result_import_sheet_id', $sheets->pluck('id'))->delete();

            foreach ($plan->rows as $index => $row) {
                $match = $plan->matches[$index];
                $sheet = $sheets[$match['sheet']];

                ResultImportRow::create(
                    ResultImportRow::fromPlan($row, $match) + ['result_import_sheet_id' => $sheet->id],
                );
            }

            $import->update(['status' => ImportStatus::Review]);
        });
    }
}
