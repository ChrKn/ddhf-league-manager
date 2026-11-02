<?php

namespace App\Import;

use App\Import\Formats\SheetFormat;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Takes uploaded files in: reads every one, and turns each tournament in them into a sheet.
 *
 * Used where files enter an import - the first upload, a file added later, a file replaced - so
 * all three say the same things about the same file.
 *
 * Every file is read to the end, and so is every sheet in it. What is wrong is collected rather
 * than thrown at the first occasion: a file that goes back to its sender should go back once.
 * Problems stop the intake, because only a different file helps. Notes do not, because the field
 * size they are about is settled in the mapping form anyway.
 */
final class SheetIntake
{
    /**
     * @var list<array{original_name: string, stored_path: string, worksheet: string|null,
     *                 participants: int|null, stated_participants: int|null, row_count: int,
     *                 notes: list<string>}>
     */
    public array $sheets = [];

    /** @var list<string> */
    public array $problems = [];

    /** @var list<string> */
    public array $notes = [];

    /**
     * @param  array<string, string>  $uploads  stored path => the name it was sent under
     * @param  class-string<SheetFormat>  $format
     * @param  list<string>  $taken  names of sheets the import already has
     */
    public static function read(array $uploads, string $format, array $taken = []): self
    {
        $intake = new self();
        $seen = array_fill_keys($taken, true);

        foreach ($uploads as $path => $name) {
            try {
                $file = $format::inspect(Storage::disk('local')->path($path));
            } catch (RuntimeException $exception) {
                $intake->problems[] = "\"{$name}\": " . $exception->getMessage();
                continue;
            }

            foreach ($file->notes as $note) {
                $intake->notes[] = "\"{$name}\": {$note}";
            }

            foreach ($file->sheets as $sheet) {
                $label = $sheet->label($name);

                foreach ($sheet->problems as $problem) {
                    $intake->problems[] = "\"{$label}\": {$problem}";
                }

                foreach ($sheet->notes as $note) {
                    $intake->notes[] = "\"{$label}\": {$note}";
                }

                if (isset($seen[$label])) {
                    // Rows are told apart by this name, so two of them would land in one
                    // tournament.
                    $intake->problems[] = "Zwei Dateien tragen den Namen \"{$label}\".";
                }

                $seen[$label] = true;

                $intake->sheets[] = [
                    'original_name'       => $label,
                    'stored_path'         => $path,
                    'worksheet'           => $sheet->worksheet,
                    // The proposal, and the decision until somebody makes another one.
                    'participants'        => $sheet->participants,
                    'stated_participants' => $sheet->participants,
                    'row_count'           => count($sheet->rows),
                    'notes'               => $sheet->notes,
                ];
            }
        }

        return $intake;
    }

    public function passes(): bool
    {
        return $this->problems === [];
    }

    /**
     * Removes the uploads again. Nothing was taken in, so nothing may be left lying in the store.
     *
     * @param  array<string, string>  $uploads  stored path => name
     */
    public static function discard(array $uploads): void
    {
        foreach (array_keys($uploads) as $path) {
            Storage::disk('local')->delete($path);
        }
    }
}
