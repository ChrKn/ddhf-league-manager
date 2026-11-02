<?php

namespace App\Import;

use App\Import\Formats\SheetFormats;
use App\Models\ResultImport;
use App\Models\ResultImportRow;
use App\Models\ResultImportSheet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Changes to the files of an import that is already under way.
 *
 * A file that turns out to be wrong used to mean deleting the import, correcting the file and
 * starting over - season, event and every choice made so far included. Now the one file is
 * exchanged and the rest stays as it was.
 *
 * Either change puts the import back to the start of matching: the rows of an earlier run were
 * matched against files that are no longer the ones there, and somebody in two files is only
 * recognised as one person when all files are matched together.
 */
final class ImportFiles
{
    /**
     * More tournaments for the same event.
     *
     * @param  array<string, string>  $uploads  stored path => name as sent
     */
    public static function add(ResultImport $import, array $uploads): SheetIntake
    {
        self::assertOpen($import, $uploads);

        $intake = SheetIntake::read(
            $uploads,
            SheetFormats::byKey($import->format),
            $import->sheets()->pluck('original_name')->all(),
        );

        if (!$intake->passes()) {
            SheetIntake::discard($uploads);

            return $intake;
        }

        DB::transaction(function () use ($import, $intake): void {
            foreach ($intake->sheets as $sheet) {
                ResultImportSheet::create($sheet + ['result_import_id' => $import->id]);
            }

            self::backToMatching($import);
        });

        return $intake;
    }

    /**
     * A corrected version of a file already in the import.
     *
     * A tournament that is in the new file again keeps what was settled for it - standing,
     * system, partial, and a field size somebody entered by hand. Recognised by its sheet, or,
     * where both files hold a single tournament, simply as that one: a corrected file is often
     * saved under a new name. Tournaments the new file no longer has are taken out.
     *
     * @param  string  $storedPath  the file to replace
     * @param  array<string, string>  $uploads  stored path => name as sent
     */
    public static function replace(ResultImport $import, string $storedPath, array $uploads): SheetIntake
    {
        self::assertOpen($import, $uploads);

        $old = $import->sheets()->where('stored_path', $storedPath)->get();

        if ($old->isEmpty()) {
            SheetIntake::discard($uploads);

            throw new RuntimeException('Diese Datei gehört nicht (mehr) zum Import.');
        }

        $intake = SheetIntake::read(
            $uploads,
            SheetFormats::byKey($import->format),
            $import->sheets()->where('stored_path', '!=', $storedPath)->pluck('original_name')->all(),
        );

        if (!$intake->passes()) {
            SheetIntake::discard($uploads);

            return $intake;
        }

        DB::transaction(function () use ($import, $old, $intake, $storedPath): void {
            $single = $old->count() === 1 && count($intake->sheets) === 1;
            $unmatched = $old->keyBy(fn (ResultImportSheet $sheet) => (string) $sheet->worksheet);

            foreach ($intake->sheets as $attributes) {
                $match = $single
                    ? $unmatched->shift()
                    : $unmatched->pull((string) $attributes['worksheet']);

                if (!$match) {
                    ResultImportSheet::create($attributes + ['result_import_id' => $import->id]);
                    continue;
                }

                // A field size nobody touched follows the corrected file; one entered by hand is
                // a decision, and stays.
                if ($match->participants !== null && $match->participants !== $match->stated_participants) {
                    unset($attributes['participants']);
                }

                $match->update($attributes);
            }

            // Deleting releases the old file once the last of these is gone.
            $unmatched->each(fn (ResultImportSheet $sheet) => $sheet->delete());

            // Where every tournament found its successor, nothing was deleted to do it. Asked by
            // the path rather than by the old sheets, which by now point at the new file.
            if (!ResultImportSheet::where('stored_path', $storedPath)->exists()) {
                Storage::disk('local')->delete($storedPath);
            }

            self::backToMatching($import);
        });

        return $intake;
    }

    private static function backToMatching(ResultImport $import): void
    {
        ResultImportRow::whereIn('result_import_sheet_id', $import->sheets()->pluck('id'))->delete();

        $import->update(['status' => ImportStatus::Draft]);
    }

    /** @param array<string, string> $uploads removed again when the answer is no */
    private static function assertOpen(ResultImport $import, array $uploads): void
    {
        if (!$import->status->isOpen()) {
            SheetIntake::discard($uploads);

            throw new RuntimeException('Dieser Import ist abgeschlossen. Erst zurücknehmen, dann Dateien ändern.');
        }
    }
}
