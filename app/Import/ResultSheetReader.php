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
 * Reads a result sheet in the DDHF submission template.
 *
 * One file holds one tournament. Rows two to six are template scaffolding - a title line, the
 * tournament system, a "Beispiel" block - and the data follows underneath:
 *
 *     Platzierung | Name                  | Gruppe          | Verband | RL-Gender | Turnierzone | Turniergröße | Turniersystem
 *     "Berlin HEMA Cup Säbel" "Offen" …  |                 |         |           |             |               | Round Robin
 *     Beispiel
 *     Platzierung | Name                  | Gruppe          | Verband | RL-Gender | Turnierzone | Turniergröße
 *     1           | Karl Kaiser  | Hammerschlag Testhausen |         | Offen     | Nord        | 43
 *
 * The header is checked rather than assumed. This used to accept two further shapes, put
 * together by hand for a single event each; anything that does not match the template now has
 * to be copied into it before the import, which is the only way the columns stay meaningful.
 */
class ResultSheetReader
{
    /** The header the template has to start with. */
    public const COLUMNS = [
        'Platzierung', 'Name', 'Gruppe', 'Verband',
        'RL-Gender', 'Turnierzone', 'Turniergröße', 'Turniersystem',
    ];

    /**
     * @return list<array{sheet: string, row: int, placement: string, name: string, club: string,
     *                    federation: string, region: string, participants: string, system: string}>
     */
    public static function read(string $path): array
    {
        $cells = self::cells($path);

        self::assertTemplate($path, $cells[1] ?? []);

        $rows = [];

        foreach (self::afterTheHeadings($cells) as $number => $row) {
            // Trailing empty rows and the leftovers of the example block have no name.
            if (($row[1] ?? '') === '') {
                continue;
            }

            $rows[] = [
                // The sheet is called "Tabelle1" in every file, so the tournament is mapped by
                // file name instead.
                'sheet' => pathinfo($path, PATHINFO_FILENAME),
                'row'   => $number,
                // Handed through as written. A withdrawal shows up as text rather than a number,
                // and that has to reach the reviewer instead of being dropped.
                'placement'    => $row[0] ?? '',
                'name'         => self::withoutStartingNumber($row[1]),
                'club'         => $row[2] ?? '',
                'federation'   => $row[3] ?? '',
                'region'       => $row[5] ?? '',
                'participants' => $row[6] ?? '',
                // How it was fenced. It decides whether a placement is a rank or the worst place
                // of an elimination round, so it belongs on the tournament rather than nowhere.
                'system'       => $row[7] ?? '',
            ];
        }

        return $rows;
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

    /** @param list<string> $header */
    private static function assertTemplate(string $path, array $header): void
    {
        $found = array_slice($header, 0, count(self::COLUMNS));

        if ($found === self::COLUMNS) {
            return;
        }

        throw new RuntimeException(
            "Die Datei entspricht nicht der DDHF-Vorlage: {$path}\n"
            . '  erwartete Spalten: ' . implode(' | ', self::COLUMNS) . "\n"
            . '  gefunden:          ' . (implode(' | ', $found) ?: '(leer)') . "\n"
            . 'Ergebnisse in einem anderen Aufbau müssen vor dem Import in die Vorlage kopiert werden.'
        );
    }

    /**
     * The delimiters a csv of this template turns up with. Excel writes the one the machine's
     * locale asks for, which is why the file itself has to be asked rather than the sender.
     */
    private const DELIMITERS = [';', ',', "\t"];

    /**
     * @return array<int, list<string>> keyed by the row number in the sheet
     */
    private static function cells(string $path): array
    {
        if (!is_file($path)) {
            throw new RuntimeException("Datei nicht gefunden: {$path}");
        }

        // The extension is not asked. A workbook saved as .csv and a csv named .xlsx are both
        // things that happen, and the file itself can be made to say which it is.
        $sheets = self::asWorkbook($path) ?? self::asDelimitedText($path);

        if ($sheets === null) {
            throw new RuntimeException(
                "Die Datei lässt sich weder als xlsx noch als CSV lesen: {$path}."
            );
        }

        if (count($sheets) > 1) {
            // One file, one tournament. Silently reading only the first sheet would drop
            // results without anyone noticing.
            throw new RuntimeException(
                "Die Arbeitsmappe hat mehrere gefüllte Blätter: {$path}\n"
                . '  ' . implode(', ', array_keys($sheets)) . "\n"
                . 'Die Vorlage sieht ein Turnier je Datei vor.'
            );
        }

        return reset($sheets) ?: [];
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
