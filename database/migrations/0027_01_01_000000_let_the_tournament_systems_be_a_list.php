<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The tournament system becomes a choice from a list instead of whatever the sheet said.
 *
 * It was free text because it came from a "Turniersystem" column that organisers filled in their
 * own words. That column is not in the DDHF template at all - the template has seven columns and
 * names the system in prose on the title line - so every delivered sheet had to be edited before
 * it could be imported, only to carry a value that was then normalised by hand anyway. The values
 * on record are already down to three spellings.
 *
 * So the system is now picked while mapping the files, from this table, and the table can grow
 * through the panel when a tournament is fenced some new way.
 *
 * Restrict rather than set null on tournaments: a system that disappears from under a tournament
 * would leave its list of rounds reading like a recording error, which is what the column is there
 * to prevent.
 *
 * The same migration teaches an import sheet which worksheet of its file it is. A workbook with
 * one sheet per tournament is how several organisers deliver, and the template's own "Beispiel"
 * sheet means even an untouched template has more than one.
 */
return new class extends Migration
{
    /** What is on record today, plus the one the template offers that nobody has delivered yet. */
    private const SEEDED = [
        'Alle gegen alle',
        'Turnierbaum',
        'Turnierbaum mit Vorrunde',
        'Doppel-K.-o. mit Vorrunde',
    ];

    public function up(): void
    {
        Schema::create('tournament_formats', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        foreach (self::SEEDED as $index => $name) {
            $this->format($name, ($index + 1) * 10);
        }

        Schema::table('tournaments', function (Blueprint $table) {
            $table->foreignId('tournament_format_id')->nullable()->after('region')
                ->constrained()->restrictOnDelete();
        });

        // A spelling that is not on the list is kept as an entry of its own rather than dropped:
        // somebody wrote it down, and the list can be tidied in the panel afterwards.
        foreach (DB::table('tournaments')->whereNotNull('format')->distinct()->pluck('format') as $name) {
            DB::table('tournaments')
                ->where('format', $name)
                ->update(['tournament_format_id' => $this->format($name)]);
        }

        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn('format');
        });

        Schema::table('result_import_sheets', function (Blueprint $table) {
            // Null for a file holding only one tournament, which is read whatever its sheet is
            // called - the template's is "Tabelle1" or "Ergebnisse hier eintragen".
            $table->string('worksheet', 250)->nullable()->after('stored_path');
            // What the file said, next to what was decided. `participants` is now the decision,
            // made in the mapping form; this is the proposal it started from, and what is shown
            // there when the two differ.
            $table->unsignedInteger('stated_participants')->nullable()->after('participants');
            // How many results the sheet lists, which is what the field size is checked against.
            $table->unsignedInteger('row_count')->nullable()->after('stated_participants');
            // What reading the file turned up that the mapping form can settle - a field size
            // stated twice, or not at all. Kept so it can be shown where it is settled.
            $table->json('notes')->nullable()->after('stated_participants');
            $table->foreignId('tournament_format_id')->nullable()->after('notes')
                ->constrained()->nullOnDelete();
        });

        // Working material, so only what matches is carried over. An open import asks again.
        foreach (DB::table('result_import_sheets')->whereNotNull('format')->distinct()->pluck('format') as $name) {
            DB::table('result_import_sheets')
                ->where('format', $name)
                ->update(['tournament_format_id' => DB::table('tournament_formats')->where('name', $name)->value('id')]);
        }

        Schema::table('result_import_sheets', function (Blueprint $table) {
            $table->dropColumn('format');
        });
    }

    public function down(): void
    {
        Schema::table('result_import_sheets', function (Blueprint $table) {
            $table->string('format', 100)->nullable()->after('participants');
        });

        Schema::table('tournaments', function (Blueprint $table) {
            $table->string('format', 120)->nullable()->after('region');
        });

        foreach (DB::table('tournament_formats')->pluck('name', 'id') as $id => $name) {
            DB::table('tournaments')->where('tournament_format_id', $id)->update(['format' => $name]);
            DB::table('result_import_sheets')->where('tournament_format_id', $id)->update(['format' => $name]);
        }

        Schema::table('result_import_sheets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tournament_format_id');
            $table->dropColumn(['worksheet', 'stated_participants', 'row_count', 'notes']);
        });

        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tournament_format_id');
        });

        Schema::dropIfExists('tournament_formats');
    }

    /** The id of the entry by this name, created where there is none. */
    private function format(string $name, ?int $sort = null): int
    {
        $id = DB::table('tournament_formats')->where('name', $name)->value('id');

        if ($id !== null) {
            return $id;
        }

        $sort ??= (int) DB::table('tournament_formats')->max('sort') + 10;

        return DB::table('tournament_formats')->insertGetId([
            'name'       => $name,
            'sort'       => $sort,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
