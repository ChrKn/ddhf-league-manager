<?php

namespace Tests\Unit\Import;

use App\Import\ResultSheetReader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Runs against fixtures with invented people rather than a delivered result sheet. Those carry
 * names, clubs and placements, so they stay out of the repository - and a test bound to a file
 * that is deleted after the import skips itself instead of failing.
 */
class ResultSheetReaderTest extends TestCase
{
    private function fixture(string $name): string
    {
        return __DIR__ . "/../../fixtures/import/{$name}.xlsx";
    }

    public function test_the_columns_are_mapped(): void
    {
        $first = ResultSheetReader::read($this->fixture('vorlage'))[0];

        $this->assertSame('vorlage', $first['sheet']);
        $this->assertSame('1', $first['placement']);
        $this->assertSame('Anna Beispiel', $first['name']);
        $this->assertSame('Musterfechter', $first['club']);
        $this->assertSame('DDHF', $first['federation']);
        $this->assertSame('Nord', $first['region']);
        $this->assertSame('5', $first['participants']);
    }

    public function test_the_template_scaffolding_is_not_mistaken_for_data(): void
    {
        $names = array_column(ResultSheetReader::read($this->fixture('vorlage')), 'name');

        // Title line, "Beispiel", the repeated heading and the leftover row that only carries
        // the field size all sit among the rows.
        $this->assertCount(5, $names);
        $this->assertNotContains('Name', $names);
        $this->assertNotContains('Beispiel', $names);
        $this->assertNotContains('', $names);
    }

    public function test_the_data_is_found_below_a_single_heading_as_well(): void
    {
        // Deleting the example block is a reasonable thing for an organiser to do.
        $rows = ResultSheetReader::read($this->fixture('vorlage-ohne-beispiel'));

        $this->assertCount(2, $rows);
        $this->assertSame('Anna Beispiel', $rows[0]['name']);
    }

    public function test_the_starting_number_is_removed_from_the_name(): void
    {
        $names = array_column(ResultSheetReader::read($this->fixture('vorlage')), 'name');

        $this->assertSame(['Anna Beispiel', 'Berta Muster', 'Carl Testmann', 'Dora Übung', 'Emil Ausfall'], $names);
    }

    public function test_a_non_numeric_placement_is_handed_through_rather_than_dropped(): void
    {
        $rows = ResultSheetReader::read($this->fixture('vorlage'));
        $odd = array_values(array_filter($rows, fn ($row) => !ctype_digit($row['placement'])));

        // Dropping it silently would lose a participant and quietly change the field size.
        $this->assertCount(1, $odd);
        $this->assertSame('Verletzung/Aufgabe', $odd[0]['placement']);
        $this->assertSame('Emil Ausfall', $odd[0]['name']);
    }

    public function test_a_shared_placement_is_kept_twice(): void
    {
        $placements = array_column(ResultSheetReader::read($this->fixture('vorlage')), 'placement');

        $this->assertSame(['1', '2', '2', '4', 'Verletzung/Aufgabe'], $placements);
    }

    public function test_a_file_that_is_not_the_template_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('entspricht nicht der DDHF-Vorlage');

        ResultSheetReader::read($this->fixture('falsche-kopfzeile'));
    }

    public function test_a_workbook_with_several_sheets_is_several_tournaments(): void
    {
        $file = ResultSheetReader::inspect($this->fixture('zwei-blaetter'));

        $this->assertSame(['Rapier Offen', 'Rapier Damen+'], array_map(fn ($sheet) => $sheet->worksheet, $file->sheets));
        $this->assertSame('Anna Beispiel', $file->sheets[0]->rows[0]['name']);
        $this->assertSame('Berta Muster', $file->sheets[1]->rows[0]['name']);
        $this->assertSame('zwei-blaetter · Rapier Damen+', $file->sheets[1]->label('zwei-blaetter'));
    }

    public function test_one_sheet_of_several_is_read_by_name(): void
    {
        $rows = ResultSheetReader::read($this->fixture('zwei-blaetter'), 'Rapier Damen+');

        $this->assertSame(['Berta Muster'], array_column($rows, 'name'));
    }

    public function test_a_workbook_of_several_is_not_read_as_one(): void
    {
        // Reading only the first sheet would drop a whole tournament without a word.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('mehrere Turniere');

        ResultSheetReader::read($this->fixture('zwei-blaetter'));
    }

    public function test_the_example_sheet_of_the_template_is_left_out(): void
    {
        $file = ResultSheetReader::inspect($this->fixture('vorlage-mit-beispielblatt'));

        $this->assertCount(1, $file->sheets);
        // The only tournament left, so the file is that tournament whatever its sheet is called.
        $this->assertNull($file->sheets[0]->worksheet);
        $this->assertCount(2, $file->sheets[0]->rows);
        $this->assertStringContainsString('"Beispiel"', implode("\n", $file->notes));
    }

    public function test_the_template_as_the_federation_sends_it_has_seven_columns(): void
    {
        // No "Turniersystem" column: the template names the system in prose on the title line,
        // and puts a list of systems to choose from next to the table.
        $rows = ResultSheetReader::read($this->fixture('vorlage-ddhf'));

        $this->assertSame(['Anna Beispiel', 'Berta Muster'], array_column($rows, 'name'));
        $this->assertArrayNotHasKey('system', $rows[0]);
    }

    public function test_everything_wrong_is_collected(): void
    {
        $file = ResultSheetReader::inspect($this->fixture('mehrere-fehler'));

        [$header, $sizes] = $file->sheets;

        $this->assertStringContainsString('Kopfzeile', implode("\n", $header->problems));

        // Readable, so a matter of notes rather than problems: the mapping form settles it.
        $this->assertTrue($sizes->readable());
        $this->assertSame(3, $sizes->participants);
        $notes = implode("\n", $sizes->notes);
        $this->assertStringContainsString('keine Zahl', $notes);
        $this->assertStringContainsString('mehrere Werte: 3 (2 Zeilen), 4 (Zeile 4)', $notes);
    }

    public function test_an_empty_field_size_is_a_question_rather_than_a_problem(): void
    {
        $sheet = ResultSheetReader::inspect($this->fixture('ohne-turniergroesse'))->sheets[0];

        $this->assertTrue($sheet->readable());
        $this->assertNull($sheet->participants);
        $this->assertStringContainsString('Turniergröße ist leer', implode("\n", $sheet->notes));
    }

    public function test_a_missing_file_is_reported(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Datei nicht gefunden');

        ResultSheetReader::read($this->fixture('gibtsnicht'));
    }

    // ------------------------------------------------------------------------------------------
    // csv. The federation accepts the template as a spreadsheet or as text, and what separates
    // the columns of a text one depends on the machine it was saved from rather than on anything
    // the sender chose. So the file is asked instead of the sender.
    // ------------------------------------------------------------------------------------------

    /** @return array{0: string, 1: callable(): void} the path, and how to clean it up */
    private function csv(string $delimiter, string $encoding = 'UTF-8'): string
    {
        $lines = [
            ResultSheetReader::COLUMNS,
            ['1', 'Anna Beispiel (7)', 'Musterfechter', 'DDHF', 'Damen+', 'Nord', '2', 'Turnierbaum'],
            ['2', 'Dora Übung (12)', 'Probeklingen', 'DDHF', 'Offen', 'Nord', '2', 'Turnierbaum'],
        ];

        $text = implode("\n", array_map(fn (array $line) => implode($delimiter, $line), $lines));

        if ($encoding !== 'UTF-8') {
            $text = mb_convert_encoding($text, $encoding, 'UTF-8');
        }

        $path = sys_get_temp_dir() . '/ddhf-vorlage-' . bin2hex(random_bytes(4)) . '.csv';
        file_put_contents($path, $text);

        $this->paths[] = $path;

        return $path;
    }

    /** @var list<string> */
    private array $paths = [];

    protected function tearDown(): void
    {
        foreach ($this->paths as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }

    public static function delimiters(): array
    {
        return ['Semikolon' => [';'], 'Komma' => [','], 'Tabulator' => ["\t"]];
    }

    #[DataProvider('delimiters')]
    public function test_a_csv_is_read_whichever_delimiter_it_uses(string $delimiter): void
    {
        $rows = ResultSheetReader::read($this->csv($delimiter));

        $this->assertCount(2, $rows);
        $this->assertSame('Anna Beispiel', $rows[0]['name']);
        $this->assertSame('Musterfechter', $rows[0]['club']);
        $this->assertSame('2', $rows[0]['participants']);
    }

    public function test_a_csv_saved_by_excel_keeps_its_umlauts(): void
    {
        // Excel on a German machine writes cp1252 unless told otherwise. Read as UTF-8 the name
        // comes out broken, and a list of people is the one place that may not happen quietly.
        $rows = ResultSheetReader::read($this->csv(';', 'Windows-1252'));

        $this->assertSame('Dora Übung', $rows[1]['name']);
    }

    public function test_a_csv_that_is_not_the_template_is_rejected_as_one(): void
    {
        $path = sys_get_temp_dir() . '/ddhf-falsch-' . bin2hex(random_bytes(4)) . '.csv';
        file_put_contents($path, "Platz;Name;Verein\n1;Anna Beispiel;Musterfechter");
        $this->paths[] = $path;

        // Not "unreadable": it was read, and what it says is the wrong header. That is the
        // message the sender can act on.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('entspricht nicht der DDHF-Vorlage');

        ResultSheetReader::read($path);
    }
}
