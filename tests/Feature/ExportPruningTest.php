<?php

namespace Tests\Feature;

use App\Models\Export;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * A finished export is deleted after four weeks, with its files and its notification.
 *
 * Filament's own model declares Prunable and implements no prunable(), so nothing was ever
 * deleted and the retention was nobody's decision. Three things have to go together: the row,
 * the files it points at, and the notification carrying the download links - a link that
 * survives its file is worse than no link.
 */
class ExportPruningTest extends TestCase
{
    use RefreshDatabase;

    private function export(string $ageInWeeks): Export
    {
        $export = Export::create([
            'completed_at'   => now(),
            'file_disk'      => 'local',
            'file_name'      => 'export',
            'exporter'       => 'App\Filament\Exports\FencerExporter',
            'processed_rows' => 1,
            'total_rows'     => 1,
            'successful_rows' => 1,
            'user_id'        => User::create([
                'name'     => 'Prüferin',
                'email'    => Str::random(8) . '@example.test',
                'password' => 'x',
            ])->id,
        ]);

        $export->forceFill(['created_at' => now()->subWeeks((float) $ageInWeeks)])->save();

        Storage::disk('local')->put($export->getFileDirectory() . '/export.csv', 'a,b');

        // The notification Filament sends when the export finishes, in the shape it stores it:
        // JSON, with the slashes in the URL escaped.
        DB::table('notifications')->insert([
            'id'              => Str::uuid()->toString(),
            'type'            => 'Filament\Notifications\DatabaseNotification',
            'notifiable_type' => User::class,
            'notifiable_id'   => $export->user_id,
            'data'            => json_encode([
                'body'    => 'Your export has completed.',
                'actions' => [[
                    'label' => 'Lade .csv herunter',
                    'url'   => "/filament/exports/{$export->getKey()}/download?format=csv",
                ]],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $export;
    }

    public function test_an_export_older_than_four_weeks_goes_with_its_files_and_its_notification(): void
    {
        $old = $this->export('5');
        $recent = $this->export('3');

        $this->assertSame(2, Export::count());
        $this->assertSame(2, DB::table('notifications')->count());

        $this->artisan('model:prune', ['--model' => [Export::class]])->assertSuccessful();

        $this->assertNull(Export::find($old->getKey()));
        $this->assertNotNull(Export::find($recent->getKey()));

        // The files, or the row would only be a forgotten pointer to a csv nobody deletes.
        $this->assertFalse(Storage::disk('local')->directoryExists($old->getFileDirectory()));
        $this->assertTrue(Storage::disk('local')->directoryExists($recent->getFileDirectory()));

        // And the notification, because its links now lead nowhere. This is the part that
        // silently did nothing at first: the column holds JSON with escaped slashes, so a LIKE
        // pattern written with plain ones matched no row at all.
        $this->assertSame(1, DB::table('notifications')->count());
        $remaining = DB::table('notifications')->value('data');
        $this->assertStringContainsString("/exports/{$recent->getKey()}/download", json_decode($remaining, true)['actions'][0]['url']);
    }

    public function test_the_cut_is_four_weeks(): void
    {
        $this->assertSame(4, Export::KEEP_FOR_WEEKS);

        $just = $this->export('3.9');
        $over = $this->export('4.1');

        $this->artisan('model:prune', ['--model' => [Export::class]])->assertSuccessful();

        $this->assertNotNull(Export::find($just->getKey()));
        $this->assertNull(Export::find($over->getKey()));
    }

    public function test_a_notification_of_another_export_is_left_alone(): void
    {
        $old = $this->export('5');

        // A notification whose id merely contains the pruned one as a substring - 4 inside 42.
        DB::table('notifications')->insert([
            'id'              => Str::uuid()->toString(),
            'type'            => 'Filament\Notifications\DatabaseNotification',
            'notifiable_type' => User::class,
            'notifiable_id'   => $old->user_id,
            'data'            => json_encode(['actions' => [[
                'url' => "/filament/exports/{$old->getKey()}9/download?format=csv",
            ]]]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('model:prune', ['--model' => [Export::class]])->assertSuccessful();

        $this->assertSame(1, DB::table('notifications')->count());
    }
}
