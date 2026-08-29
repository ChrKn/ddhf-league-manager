<?php

namespace App\Console\Commands;

use App\Import\ImportPlan;
use App\Import\ImportPlanner;
use App\Import\MatchConfidence;
use App\Models\Tournament;
use Illuminate\Console\Command;

/**
 * First half of the import on the console: matches outside result sheets against the database
 * and writes a review file. Nothing is written to the database here.
 *
 * The matching itself lives in App\Import\ImportPlanner, which the panel uses as well. This
 * command is the way in for someone at a terminal, and the fallback for when the panel cannot
 * be reached.
 *
 * All files of an event belong in one run. Someone who entered several tournaments appears in
 * several files, and the protection against duplicate fencers in the apply step only reaches
 * within a single run.
 */
class PrepareResultImport extends Command
{
    protected $signature = 'import:results-prepare
        {file* : Paths to the xlsx files, one tournament each}
        {--tournament=* : File name and tournament, e.g. --tournament="DDHF Turnier Rapier Open=T7H565C5"}
        {--partial=* : File name whose field is known to be recorded only in part, e.g. --partial="Ochsenstich 2023"}
        {--out=storage/exchange/import-review.csv : Where to write the review file}';

    protected $description = 'Vergleicht eine Ergebnisdatei mit der Datenbank und schreibt eine Prüfdatei';

    /** The columns of the review file. */
    public const COLUMNS = ImportPlanner::COLUMNS;

    public function handle(): int
    {
        try {
            $rows = ImportPlanner::read($this->argument('file'));
        } catch (\RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $tournaments = $this->tournamentsBySheet($rows);

        if ($tournaments === null) {
            return self::FAILURE;
        }

        $consistency = ImportPlanner::check($rows, $tournaments, $this->option('partial'));

        foreach ($consistency->notes as $note) {
            $this->warn($note);
        }

        if (!$consistency->agrees()) {
            foreach ($consistency->problems as $problem) {
                $this->error($problem);
            }

            return self::FAILURE;
        }

        $plan = (new ImportPlanner())->plan($rows, $tournaments);

        $this->write($this->option('out'), $plan->rows);
        $this->report($plan);

        return self::SUCCESS;
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @return array<string, Tournament>|null
     */
    private function tournamentsBySheet(array $rows): ?array
    {
        $sheets = array_values(array_unique(array_column($rows, 'sheet')));
        $mapping = [];

        foreach ($this->option('tournament') as $pair) {
            if (!str_contains($pair, '=')) {
                $this->error("--tournament erwartet \"Dateiname=Turnier-ID\", erhalten: {$pair}");

                return null;
            }

            [$sheet, $publicId] = explode('=', $pair, 2);
            $tournament = Tournament::where('public_id', trim($publicId))->first();

            if (!$tournament) {
                $this->error("Turnier nicht gefunden: {$publicId}");

                return null;
            }

            $mapping[trim($sheet)] = $tournament;
        }

        $missing = array_diff($sheets, array_keys($mapping));

        if ($missing !== []) {
            $this->error('Für diese Dateien fehlt die Turnierzuordnung:');

            foreach ($missing as $sheet) {
                $this->line("  --tournament=\"{$sheet}=<Turnier-ID>\"");
            }

            return null;
        }

        return $mapping;
    }

    /** @param list<array<string, string>> $rows */
    private function write(string $path, array $rows): void
    {
        $directory = dirname($path);

        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $handle = fopen($path, 'w');

        // Excel needs the byte order mark to read the umlauts as UTF-8.
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, self::COLUMNS, ';');

        foreach ($rows as $row) {
            fputcsv($handle, array_map(fn ($column) => $row[$column], self::COLUMNS), ';');
        }

        fclose($handle);
    }

    private function report(ImportPlan $plan): void
    {
        $this->newLine();
        $this->info('Prüfdatei geschrieben: ' . $this->option('out'));
        $this->line(count($plan->rows) . ' Zeilen');
        $this->newLine();

        foreach (['fechter' => 'Fechter', 'verein' => 'Vereine'] as $key => $heading) {
            $this->line("<comment>{$heading}</comment>");

            foreach (MatchConfidence::cases() as $case) {
                $n = $plan->counts[$key][$case->value] ?? 0;

                if ($n > 0) {
                    $this->line(sprintf('  %-16s %d', $case->label(), $n));
                }
            }
        }

        $known = count(array_filter($plan->rows, fn ($row) => $row['hinweis'] !== ''));

        if ($known > 0) {
            $this->newLine();
            $this->warn("{$known} Zeilen haben in diesem Turnier schon ein Ergebnis und stehen auf \"skip\".");
        }

        $open = $plan->open();

        $this->newLine();

        if ($open === 0) {
            $this->info('Alle Zeilen sind eindeutig, "aktion" ist überall vorbelegt.');

            return;
        }

        $this->warn("{$open} Zeilen brauchen eine Entscheidung in der Spalte \"aktion\".");
        $this->line('  use    = den zugeordneten Fechter verwenden (fechter_id ggf. vorher korrigieren)');
        $this->line('  create = Fechter neu anlegen - dazu fechter_id leeren');
        $this->line('  skip   = Zeile überspringen');
        $this->newLine();
        // The column decides the fencer and nothing else, and it says so nowhere in the file.
        $this->line('Der Verein hat keine Aktion: ist verein_id gesetzt, wird der Verein verwendet,');
        $this->line('ist die Spalte leer, wird er unter der Schreibweise der Datei angelegt.');
    }
}
