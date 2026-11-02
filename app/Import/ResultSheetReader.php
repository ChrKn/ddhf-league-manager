<?php

namespace App\Import;

use OpenSpout\Common\Exception\OpenSpoutException;
use OpenSpout\Common\Helper\EncodingHelper;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\Exception\ReaderException;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use RuntimeException;

/**
 * Reads result sheets in the DDHF submission template.
 *
 * One worksheet holds one tournament. A file may bring a single one or one per sheet - organisers
 * deliver both, and the template itself comes with an extra sheet called "Beispiel", which is
 * left out. Rows two to six are template scaffolding - a title line, a "Beispiel" block - and the
 * data follows underneath:
 *
 *     Platzierung | Name                  | Gruppe          | Verband | RL-Gender | Turnierzone | Turniergröße
 *     "Berlin HEMA Cup Säbel" "Offen" "2026" "Round Robin" …
 *     Beispiel
 *     Platzierung | Name                  | Gruppe          | Verband | RL-Gender | Turnierzone | Turniergröße
 *     1           | Karl Kaiser  | Hammerschlag Testhausen |         | Offen     | Nord        | 43
 *
 * The header is checked rather than assumed; anything to the right of it is the template's own
 * margin - arrows, the list of tournament systems to choose from - and is not read. How the
 * tournament was fenced is chosen while mapping the files, not taken from the sheet: the template
 * names it only in prose on the title line.
 *
 * Reading does not stop at the first thing wrong. inspect() says everything it finds, per sheet,
 * so a file has to go back to its sender once rather than once per mistake.
 */
class ResultSheetReader
{
    /** The header the template has to start with. */
    public const COLUMNS = [
        'Platzierung', 'Name', 'Gruppe', 'Verband',
        'RL-Gender', 'Turnierzone', 'Turniergröße',
    ];

    /** The template's sheet of worked examples. Its rows are invented and never results. */
    private const EXAMPLE_SHEET = 'beispiel';

    /**
     * Every tournament in the file, each with what is wrong with it.
     *
     * @throws RuntimeException only when the file cannot be read at all
     */
    public static function inspect(string $path): ReadFile
    {
        $file = pathinfo($path, PATHINFO_FILENAME);
        [$sheets, $notes] = self::tournamentSheets($path);

        // A file with one tournament is that tournament, whatever its sheet happens to be called:
        // "Tabelle1" in some, "Ergebnisse hier eintragen" in the template.
        $single = count($sheets) === 1;

        $read = [];

        foreach ($sheets as $name => $cells) {
            $worksheet = $single ? null : (string) $name;
            $read[] = self::inspectSheet($worksheet, $cells, (new ReadSheet($worksheet, [], null))->label($file));
        }

        if ($read === []) {
            $read[] = new ReadSheet(null, [], null, ['Die Datei enthält keine Ergebnisse.']);
        }

        return new ReadFile($read, $notes);
    }

    /**
     * The rows of one tournament, for callers that take a file as it is or not at all.
     *
     * @param  string|null  $worksheet  which sheet, where the file holds several
     * @return list<array{sheet: string, row: int, placement: string, name: string, club: string,
     *                    federation: string, region: string, participants: string}>
     *
     * @throws RuntimeException when the sheet has a problem, or the file holds several and none
     *                          was named
     */
    public static function read(string $path, ?string $worksheet = null): array
    {
        $file = self::inspect($path);

        if ($worksheet === null && count($file->sheets) > 1) {
            throw new RuntimeException(
                "Die Arbeitsmappe enthält mehrere Turniere: {$path}\n"
                . '  ' . implode(', ', array_map(fn (ReadSheet $sheet) => $sheet->worksheet, $file->sheets)) . "\n"
                . 'Hier wird ein Turnier je Datei gelesen.'
            );
        }

        $sheet = $worksheet === null
            ? $file->sheets[0]
            : (array_values(array_filter($file->sheets, fn (ReadSheet $s) => $s->worksheet === $worksheet))[0]
                // A file of one tournament stores no sheet name; asked by name, it is still that one.
                ?? (count($file->sheets) === 1 ? $file->sheets[0] : null));

        if ($sheet === null) {
            throw new RuntimeException("Das Blatt \"{$worksheet}\" ist in der Datei nicht mehr da: {$path}");
        }

        if (!$sheet->readable()) {
            throw new RuntimeException($path . ":\n  " . implode("\n  ", $sheet->problems));
        }

        return $sheet->rows;
    }

    /**
     * @param  array<int, list<string>>  $cells  keyed by row number
     */
    private static function inspectSheet(?string $worksheet, array $cells, string $label): ReadSheet
    {
        $found = array_slice($cells[1] ?? [], 0, count(self::COLUMNS));

        if ($found !== self::COLUMNS) {
            return new ReadSheet($worksheet, [], null, [
                "Die Kopfzeile entspricht nicht der DDHF-Vorlage.\n"
                . '  erwartet: ' . implode(' | ', self::COLUMNS) . "\n"
                . '  gefunden: ' . (implode(' | ', $found) ?: '(leer)') . "\n"
                . 'Ergebnisse in einem anderen Aufbau müssen vor dem Import in die Vorlage kopiert werden.',
            ]);
        }

        $rows = [];

        foreach (self::afterTheHeadings($cells) as $number => $row) {
            // Trailing empty rows and the leftovers of the example block have no name.
            if (($row[1] ?? '') === '') {
                continue;
            }

            $rows[] = [
                'sheet' => $label,
                'row'   => $number,
                // Handed through as written. A withdrawal shows up as text rather than a number,
                // and that has to reach the reviewer instead of being dropped.
                'placement'    => $row[0] ?? '',
                'name'         => self::withoutStartingNumber($row[1]),
                'club'         => $row[2] ?? '',
                'federation'   => $row[3] ?? '',
                'region'       => $row[5] ?? '',
                'participants' => $row[6] ?? '',
            ];
        }

        if ($rows === []) {
            return new ReadSheet($worksheet, [], null, ['Unter der Kopfzeile stehen keine Ergebnisse.']);
        }

        [$participants, $notes] = self::fieldSize($rows);

        return new ReadSheet($worksheet, $rows, $participants, [], $notes);
    }

    /**
     * What the field size column says, and everything odd about it.
     *
     * Every row repeats the same number in the template. Where they disagree or are missing, the
     * person mapping the files decides, so this only proposes the most frequent value and says
     * which rows said otherwise.
     *
     * @param  list<array<string, string|int>>  $rows
     * @return array{0: int|null, 1: list<string>}
     */
    private static function fieldSize(array $rows): array
    {
        $byValue = [];
        $notNumbers = [];
        $empty = 0;

        foreach ($rows as $row) {
            $value = trim((string) $row['participants']);

            if ($value === '') {
                $empty++;
            } elseif (ctype_digit($value) && (int) $value > 0) {
                $byValue[(int) $value][] = $row['row'];
            } else {
                $notNumbers[] = "„{$value}“ (Zeile {$row['row']})";
            }
        }

        $notes = [];

        if ($notNumbers !== []) {
            $notes[] = 'Turniergröße ist keine Zahl: ' . implode(', ', array_slice($notNumbers, 0, 5))
                . (count($notNumbers) > 5 ? ' …' : '') . '.';
        }

        if ($byValue === []) {
            $notes[] = 'Die Spalte Turniergröße ist leer. Die Teilnehmerzahl bitte beim Zuordnen eintragen.';

            return [null, $notes];
        }

        // The most frequent value; on a tie the first one written, which is the winner's row.
        uasort($byValue, fn (array $a, array $b) => count($b) <=> count($a));
        $proposed = array_key_first($byValue);

        if (count($byValue) > 1) {
            $notes[] = 'Die Spalte Turniergröße nennt mehrere Werte: ' . implode(', ', array_map(
                fn (int $value, array $lines) => "{$value} (" . self::lines($lines) . ')',
                array_keys($byValue),
                $byValue,
            )) . ". Vorgeschlagen: {$proposed}.";
        } elseif ($empty > 0) {
            $notes[] = "Turniergröße fehlt in {$empty} Zeile(n). Vorgeschlagen: {$proposed}.";
        }

        return [$proposed, $notes];
    }

    /** @param list<int> $lines */
    private static function lines(array $lines): string
    {
        return count($lines) === 1
            ? "Zeile {$lines[0]}"
            : count($lines) . ' Zeilen';
    }

    /**
     * The template repeats the header above the example block, so the data starts below the
     * last one. Deleting the example block leaves a single header, which works just as well.
     *
     * @param  array<int, list<string>>  $cells
     * @return array<int, list<string>>
     */
    private static function afterTheHeadings(array $cells): array
    {
        $lastHeading = 1;

        foreach ($cells as $number => $row) {
            if (($row[0] ?? '') === self::COLUMNS[0]) {
                $lastHeading = $number;
            }
        }

        return array_filter($cells, fn (int $number) => $number > $lastHeading, ARRAY_FILTER_USE_KEY);
    }

    /** "Karl Kaiser (1212)" - the starting number says nothing about the person. */
    private static function withoutStartingNumber(string $name): string
    {
        return trim((string) preg_replace('/\s*\(\d+\)\s*$/u', '', $name));
    }

    /**
     * The delimiters a csv of this template turns up with. Excel writes the one the machine's
     * locale asks for, which is why the file itself has to be asked rather than the sender.
     */
    private const DELIMITERS = [';', ',', "\t"];

    /**
     * The filled sheets that may hold a tournament, and a note on any that were left out.
     *
     * @return array{0: array<string, array<int, list<string>>>, 1: list<string>}
     */
    private static function tournamentSheets(string $path): array
    {
        if (!is_file($path)) {
            throw new RuntimeException("Datei nicht gefunden: {$path}");
        }

        // The extension is not asked. A workbook saved as .csv and a csv named .xlsx are both
        // things that happen, and the file itself can be made to say which it is.
        $sheets = self::asWorkbook($path) ?? self::asDelimitedText($path);

        if ($sheets === null) {
            throw new RuntimeException('Die Datei lässt sich weder als xlsx noch als CSV lesen.');
        }

        $notes = [];

        foreach (array_keys($sheets) as $name) {
            if (mb_strtolower(trim((string) $name)) === self::EXAMPLE_SHEET) {
                unset($sheets[$name]);
                $notes[] = "Das Blatt \"{$name}\" der Vorlage wurde übersprungen.";
            }
        }

        return [$sheets, $notes];
    }

    /**
     * @return array<string, array<int, list<string>>>|null null when this is not a workbook
     */
    private static function asWorkbook(string $path): ?array
    {
        $reader = new XlsxReader();

        try {
            $reader->open($path);

            $sheets = self::harvest($reader, fn ($sheet) => trim($sheet->getName()));
        } catch (OpenSpoutException|ReaderException) {
            return null;
        } finally {
            // close() on a reader that never opened throws in its own right, and that exception
            // would replace the one worth reporting.
            try {
                $reader->close();
            } catch (\Throwable) {
            }
        }

        return $sheets;
    }

    /**
     * A csv, whichever of the three delimiters it was written with.
     *
     * Each is tried and the one whose first row comes out as the template wins. Where none does,
     * the one that produced the most columns is handed on, so that the reader complains about the
     * header it found rather than about the file being unreadable.
     *
     * @return array<string, array<int, list<string>>>|null
     */
    private static function asDelimitedText(string $path): ?array
    {
        $contents = file_get_contents($path);

        if ($contents === false || trim($contents) === '') {
            return null;
        }

        // Excel on a German machine writes cp1252 unless told otherwise, and a name read as
        // latin1 loses its umlauts silently - which is the one thing a list of people may not do.
        $encoding = mb_check_encoding($contents, 'UTF-8') ? EncodingHelper::ENCODING_UTF8 : 'Windows-1252';

        $best = null;
        $bestWidth = 0;

        foreach (self::DELIMITERS as $delimiter) {
            $options = new CsvOptions();
            $options->FIELD_DELIMITER = $delimiter;
            $options->ENCODING = $encoding;

            $reader = new CsvReader($options);

            try {
                $reader->open($path);
                $sheets = self::harvest($reader, fn () => 'Tabelle1');
            } catch (OpenSpoutException|ReaderException) {
                continue;
            } finally {
                try {
                    $reader->close();
                } catch (\Throwable) {
                }
            }

            $header = array_slice(reset($sheets)[1] ?? [], 0, count(self::COLUMNS));

            if ($header === self::COLUMNS) {
                return $sheets;
            }

            if (count($header) > $bestWidth) {
                $bestWidth = count($header);
                $best = $sheets;
            }
        }

        return $best;
    }

    /**
     * The filled rows of every sheet a reader offers, keyed by sheet name and row number.
     *
     * @param  callable(mixed): string  $name
     * @return array<string, array<int, list<string>>>
     */
    private static function harvest(object $reader, callable $name): array
    {
        $sheets = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            $cells = [];

            foreach ($sheet->getRowIterator() as $number => $row) {
                $values = array_map(fn ($cell) => trim((string) $cell->getValue()), $row->getCells());

                if (implode('', $values) !== '') {
                    $cells[$number] = $values;
                }
            }

            if ($cells !== []) {
                $sheets[$name($sheet)] = $cells;
            }
        }

        return $sheets;
    }
}
