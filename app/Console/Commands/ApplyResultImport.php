<?php

namespace App\Console\Commands;

use App\Import\ImportPlanner;
use App\Import\ImportSummary;
use App\Import\ImportWriter;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Second half of the import on the console: takes the reviewed file and writes it.
 *
 * The writing itself lives in App\Import\ImportWriter, which the panel uses as well. This command
 * reads the csv and reports; what it means to import a row is decided in one place only.
 */
class ApplyResultImport extends Command
{
    protected $signature = 'import:results-apply
        {file : Path to the reviewed csv}
        {--dry-run : Show what would happen and roll back}
        {--remember-aliases : Record corrected club spellings as aliases for future imports}';

    protected $description = 'Schreibt eine geprüfte Import-Datei in die Datenbank';

    /** What the "aktion" column of the review file may contain. */
    public const ACTIONS = ImportWriter::ACTIONS;

    public function handle(): int
    {
        try {
            $rows = $this->read($this->argument('file'));
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if (($problems = ImportWriter::validate($rows)) !== []) {
            $this->error('Die Datei ist noch nicht vollständig:');

            foreach ($problems as $problem) {
                $this->line("  {$problem}");
            }

            return self::FAILURE;
        }

        $writer = new ImportWriter(rememberAliases: (bool) $this->option('remember-aliases'));

        try {
            $summary = $writer->write($rows, (bool) $this->option('dry-run'));
        } catch (\Throwable $exception) {
            $this->error('Abgebrochen: ' . $exception->getMessage());

            return self::FAILURE;
        }

        $this->report($summary);

        return self::SUCCESS;
    }

    /** @return list<array<string, string>> */
    private function read(string $path): array
    {
        if (!is_file($path)) {
            throw new RuntimeException("Datei nicht gefunden: {$path}");
        }

        $handle = fopen($path, 'r');
        $header = fgetcsv($handle, escape: '');

        if ($header === false) {
            throw new RuntimeException('Die Datei ist leer.');
        }

        // Strip the byte order mark the prepare step writes for Excel.
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);

        // fgetcsv cannot guess the separator, so retry with the one we wrote.
        if (count($header) === 1) {
            rewind($handle);
            $header = fgetcsv($handle, separator: ';', escape: '');
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
            $separator = ';';
        } else {
            $separator = ',';
        }

        // "hinweis" is the planner talking to the reviewer and nothing the write needs, so a
        // review file from before it existed still goes through.
        $missing = array_diff(ImportPlanner::COLUMNS, $header, ['hinweis']);

        if ($missing !== []) {
            throw new RuntimeException('Diese Spalten fehlen: ' . implode(', ', $missing));
        }

        $rows = [];

        while (($values = fgetcsv($handle, separator: $separator, escape: '')) !== false) {
            if ($values === [null] || $values === ['']) {
                continue;
            }

            $row = array_combine($header, array_pad($values, count($header), ''));
            $rows[] = array_map(fn ($value) => trim((string) $value), $row);
        }

        fclose($handle);

        return $rows;
    }

    private function report(ImportSummary $summary): void
    {
        $this->newLine();

        if ($summary->dryRun) {
            $this->warn('Probelauf - nichts wurde geschrieben.');
            $this->newLine();
        }

        $lists = [
            'Neue Vereine' => $summary->clubs,
            'Neue Fechter' => $summary->fencers,
            'Neue Aliase'  => $summary->aliases,
        ];

        foreach ($lists as $heading => $entries) {
            if ($entries === []) {
                continue;
            }

            $this->line("<comment>{$heading} (" . count($entries) . ')</comment>');

            foreach ($entries as $entry) {
                $this->line("  {$entry}");
            }

            $this->newLine();
        }

        // Its own block and in warning colour: nothing failed, but a spelling somebody expected to
        // be remembered was not, and that only shows up two imports later.
        foreach ($summary->aliasConflicts as $conflict) {
            $this->warn($conflict);
        }

        if ($summary->aliasConflicts !== []) {
            $this->newLine();
        }

        if ($summary->duplicates > 0) {
            // Not an ordinary skip. It means these results were already there, which is what a
            // second run over the same file looks like.
            $this->warn($summary->duplicates . ' Zeilen übergangen, weil das Ergebnis bereits existierte.');
        }

        $this->info($summary->results . ' Ergebnisse ' . ($summary->dryRun ? 'würden geschrieben' : 'geschrieben'));
    }
}
