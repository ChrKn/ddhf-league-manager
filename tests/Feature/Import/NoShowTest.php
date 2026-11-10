<?php

namespace Tests\Feature\Import;

use App\Import\ImportPlanner;
use App\Import\NoShow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Rows for people who registered and never fenced, as some organisers send them.
 *
 * They must neither become a result nor count towards the field. At most they say how many had
 * registered, as a proposal for somebody to take or leave.
 */
class NoShowTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, string> a row as a sheet format hands it to the planner */
    private function row(string $name, string $placement, string $participants = '3'): array
    {
        return [
            'sheet'        => 'turnier.xlsx',
            'row'          => '2',
            'placement'    => $placement,
            'name'         => $name,
            'club'         => 'Fechtschule Musterstadt',
            'federation'   => '',
            'region'       => '',
            'participants' => $participants,
        ];
    }

    /** @return array<string, array{0: string, 1: bool}> */
    public static function cells(): array
    {
        return [
            'nicht angetreten' => ['nicht angetreten', true],
            'Nicht Angetreten' => ['Nicht Angetreten', true],
            'n.a.'             => ['n.a.', true],
            'DNS'              => ['DNS', true],
            'abgemeldet'       => ['abgemeldet', true],
            'no-show'          => ['no-show', true],
            // Fenced and gave up: part of the field, left to the reviewer as before.
            'Aufgabe'          => ['Aufgabe', false],
            'Verletzung'       => ['Verletzung/Aufgabe', false],
            'a placement'      => ['3', false],
            'pools'            => ['Vorrunde', false],
        ];
    }

    #[DataProvider('cells')]
    public function test_it_knows_a_no_show_when_it_reads_one(string $cell, bool $expected): void
    {
        $this->assertSame($expected, NoShow::marks($cell));
    }

    public function test_a_no_show_is_planned_as_skipped_and_never_anonymised(): void
    {
        $plan = (new ImportPlanner())->plan([
            $this->row('Magret Test', '1'),
            $this->row('Anonymer Fechter', 'nicht angetreten'),
        ], ['turnier.xlsx' => null]);

        $this->assertSame('skip', $plan->rows[1]['aktion']);
        $this->assertSame('', $plan->rows[1]['anonymisieren']);
        $this->assertStringContainsString('Nicht angetreten', $plan->rows[1]['hinweis']);
    }

    public function test_no_shows_do_not_count_towards_the_field(): void
    {
        // 22 angetreten, 3 abgesagt. Die Datei hat 25 Zeilen, das Feld ist 22 - und das ist
        // kein Widerspruch, den jemand durch Hochsetzen auf 25 "beheben" sollte.
        $rows = [];

        for ($place = 1; $place <= 22; $place++) {
            $rows[] = $this->row("Fechtende {$place}", (string) $place, '22');
        }

        foreach (['n.a.', 'nicht angetreten', 'abgemeldet'] as $index => $cell) {
            $rows[] = $this->row("Abgesagt {$index}", $cell, '22');
        }

        $report = ImportPlanner::check($rows, ['turnier.xlsx' => null], [], ['turnier.xlsx' => 22]);

        $this->assertSame([], $report->problems);
        $this->assertStringContainsString('3 Zeile(n) als nicht angetreten', implode("\n", $report->notes));
        $this->assertStringContainsString('25 Meldungen', implode("\n", $report->notes));

        // Asked to count them in, the check refuses: 25 would be a field nobody fenced in.
        $refused = ImportPlanner::check($rows, ['turnier.xlsx' => null], [], ['turnier.xlsx' => 25]);

        $this->assertNotSame([], $refused->problems);
    }
}
