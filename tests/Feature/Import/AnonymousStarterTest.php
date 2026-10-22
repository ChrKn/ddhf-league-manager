<?php

namespace Tests\Feature\Import;

use App\Import\ImportPlanner;
use App\Import\ImportStatus;
use App\Models\Discipline;
use App\Models\Division;
use App\Models\Event;
use App\Models\Fencer;
use App\Models\Result;
use App\Models\ResultImport;
use App\Models\ResultImportRow;
use App\Models\ResultImportSheet;
use App\Models\ScoringMatrix;
use App\Models\Season;
use App\Models\Standing;
use App\Models\Tournament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A starter the organisers had already anonymised before the file reached us.
 *
 * Such a row used to become a fencer literally called "Anonymer Fechter" - a stored name that reads
 * like the placeholder and is marked as anonymised nowhere, so everything that asks the flag took it
 * for a person. Found through the JSON, which said anonymized: false next to that name.
 */
class AnonymousStarterTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, string> a row as a sheet format hands it to the planner */
    private function sheetRow(string $name, string $placement = '5'): array
    {
        return [
            'sheet'      => 'hanseschlag.xlsx',
            'row'        => '2',
            'placement'  => $placement,
            'name'       => $name,
            'club'       => 'Fechtschule Musterstadt',
            'federation' => '',
            'system'     => '',
        ];
    }

    public function test_the_planner_ticks_the_box_for_the_placeholder_in_any_spelling(): void
    {
        $plan = (new ImportPlanner())->plan([
            $this->sheetRow('Anonymer Fechter'),
            $this->sheetRow('anonymer fechter', '6'),
            $this->sheetRow('Magret Test', '7'),
        ], ['hanseschlag.xlsx' => null]);

        $this->assertSame(
            [['create', '1'], ['create', '1'], ['create', '']],
            array_map(fn (array $row) => [$row['aktion'], $row['anonymisieren']], $plan->rows),
        );
    }

    public function test_the_migration_anonymises_the_placeholders_already_on_record(): void
    {
        // Back to before it, with the data it found there.
        $this->artisan('migrate:rollback', ['--step' => 1])->assertSuccessful();

        $tournament = Tournament::create([
            'season_id' => Season::create([
                'year'        => 2026,
                'standing_id' => Standing::create([
                    'discipline_id' => Discipline::create(['name' => 'Langes Schwert'])->id,
                    'division_id'   => Division::create(['name' => 'offen'])->id,
                ])->id,
                'scoring_matrix_id' => ScoringMatrix::create(['name' => 'Test', 'matrix' => '[]'])->id,
            ])->id,
            'event_id'          => Event::create(['name' => 'Hanseschlag', 'start_date' => '2026-05-09'])->id,
            'participant_count' => 60,
        ]);

        $placeholder = fn (bool $active) => Fencer::create([
            'first_name' => 'Anonymer',
            'last_name'  => 'Fechter',
            'is_active'  => $active,
        ]);

        // Imported over the CSV with create_inactive, over the panel with create, and one left
        // behind by an import that was taken back.
        $inactive = $placeholder(false);
        $active = $placeholder(true);
        $empty = $placeholder(true);

        // Somebody whose surname happens to be the second half, and somebody anonymised properly.
        $stefan = Fencer::create(['first_name' => 'Stefan', 'last_name' => 'Fechter', 'is_active' => true]);
        $anonymised = Fencer::create(['is_active' => false, 'anonymized_at' => '2026-01-01 00:00:00']);

        foreach ([$inactive, $active, $stefan, $anonymised] as $index => $fencer) {
            Result::create([
                'tournament_id'     => $tournament->id,
                'fencer_id'         => $fencer->id,
                'placement'         => (string) ($index + 1),
                'fencer_group_name' => 'Fechtschule Musterstadt',
            ]);
        }

        $sheet = ResultImportSheet::create([
            'result_import_id' => ResultImport::create([
                'status' => ImportStatus::Review,
                'format' => 'ddhf-vorlage',
            ])->id,
            'original_name' => 'alt.xlsx',
            'stored_path'   => 'alt.xlsx',
        ]);

        DB::table('result_import_rows')->insert([
            'result_import_sheet_id' => $sheet->id,
            'sheet_row'              => 2,
            'placement'              => '5',
            'name'                   => 'Anonymer Fechter',
            'action'                 => 'create_inactive',
        ]);

        $this->artisan('migrate')->assertSuccessful();

        foreach ([$inactive, $active] as $fencer) {
            $fencer->refresh();

            $this->assertTrue($fencer->isAnonymized());
            $this->assertNull($fencer->first_name);
            $this->assertNull($fencer->last_name);
            $this->assertFalse((bool) $fencer->is_active);
            $this->assertSame(Fencer::ANONYMISED_BY_ORGANISERS, $fencer->anonymization_reason);
            $this->assertNull($fencer->results()->sole()->fencer_group_name);
        }

        $this->assertNull($empty->fresh());

        $this->assertSame('Stefan Fechter', $stefan->fresh()->display_name);
        $this->assertSame('Fechtschule Musterstadt', $stefan->results()->sole()->fencer_group_name);
        $this->assertNull($anonymised->fresh()->anonymization_reason);

        $row = ResultImportRow::sole();
        $this->assertSame('create', $row->action);
        $this->assertTrue($row->anonymize);
    }
}
