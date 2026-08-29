<?php

namespace Tests\Feature\Filament;

use App\Filament\Exports\EventExporter;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The one spot left where a value is reshaped on its way out.
 *
 * There were two. The other sat on the fencer importer, which has since gone: the generic
 * importers were scaffolding for filling an empty database, and the live system is stocked from a
 * partial dump instead - see the readme under "Moving the data".
 */
class ExportColumnsTest extends TestCase
{
    private function exportColumn(string $name)
    {
        foreach (EventExporter::getColumns() as $column) {
            if ($column->getName() === $name) {
                return $column;
            }
        }

        $this->fail("Spalte {$name} gibt es im EventExporter nicht.");
    }

    public function test_an_exported_date_is_the_day_without_the_time(): void
    {
        // ISO, because the file is meant to be read back in.
        $this->assertSame(
            '2026-05-09',
            $this->exportColumn('start_date')->formatState(Carbon::parse('2026-05-09 14:37:00')),
        );
    }

    public function test_an_absent_date_stays_absent(): void
    {
        // The old version sliced ten characters off whatever it was given, which turned null into
        // an empty string and an empty string into a date-shaped nothing.
        $this->assertNull($this->exportColumn('end_date')->formatState(null));
    }

}
