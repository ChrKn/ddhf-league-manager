<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The event moves from the file to the import.
 *
 * It sat on the sheet because a sheet becomes a tournament and a tournament names an event. But
 * an import is the results of one event: the files that arrive together are the longsword, the
 * sabre and the rapier of the same weekend, and asking for the event once per file meant choosing
 * the same answer four times over.
 *
 * What stays on the sheet is the season, which is the part that genuinely differs between the
 * files of one event.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('result_imports', function (Blueprint $table) {
            $table->foreignId('event_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });

        // Whatever the sheets of an import already said. They can only disagree if somebody
        // deliberately pointed two files of one upload at two events, which the panel never
        // offered a reason to do.
        foreach (DB::table('result_imports')->pluck('id') as $id) {
            $eventId = DB::table('result_import_sheets')
                ->where('result_import_id', $id)
                ->whereNotNull('event_id')
                ->value('event_id');

            if ($eventId !== null) {
                DB::table('result_imports')->where('id', $id)->update(['event_id' => $eventId]);
            }
        }

        Schema::table('result_import_sheets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('event_id');
        });
    }

    public function down(): void
    {
        Schema::table('result_import_sheets', function (Blueprint $table) {
            $table->foreignId('event_id')->nullable()->after('season_id')->constrained()->nullOnDelete();
        });

        foreach (DB::table('result_imports')->whereNotNull('event_id')->get(['id', 'event_id']) as $import) {
            DB::table('result_import_sheets')
                ->where('result_import_id', $import->id)
                ->update(['event_id' => $import->event_id]);
        }

        Schema::table('result_imports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('event_id');
        });
    }
};
