<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a result say what it is instead of pretending to be a rank.
 *
 * An integer could only ever mean "finished in this position", so a bracket result had to be bent
 * into one to find its column in the points table: everybody who lost in the round of sixteen was
 * written down as sixteenth. It found the right column and it was still untrue, because four of
 * them may have finished ninth to twelfth.
 *
 * Where it actually broke is a bracket with a preliminary. A pool exit is worth one point at any
 * field size - the last column of the official table says so - but the pool ranks of the
 * Hanseschlag 2025 read as placements 17 to 42 and collected two and three points for columns
 * nobody in that phase reached.
 *
 * The column now holds one of three things, and which one it is decides how the matrix is read:
 *
 *     "17"       an all-against-all, where the placement is the rank someone finished on
 *     "last-16"  a bracket, where sixteen were still in when this one went out
 *     "pools"    a bracket with a preliminary, which this one did not survive
 *
 * Existing numbers survive as their decimal spelling and keep meaning exactly what they meant.
 * The vocabulary lives in App\Standings\Placement; sixteen characters is twice what its longest
 * value needs.
 *
 * Ordering by this column now needs Placement::sortKey() rather than SQL - "last-16" sorts before
 * "last-8" and "10" before "9" if you leave it to the collation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('results', function (Blueprint $table) {
            $table->string('placement', 16)->change();
        });
    }

    /**
     * Rounds and pool exits have no number to go back to, so anything that is not already one is
     * left for a human. Reversing this migration on data that carries keys would silently invent
     * placements, and that is the very thing it was written to stop.
     */
    public function down(): void
    {
        // Counted in PHP rather than with REGEXP, which SQLite - and so the test suite - has no
        // built-in for.
        $keys = DB::table('results')
            ->pluck('placement')
            ->reject(fn ($placement) => ctype_digit((string) $placement))
            ->count();

        if ($keys > 0) {
            throw new RuntimeException(
                "{$keys} Ergebnisse tragen eine Runde oder \"pools\" statt einer Platzierung. "
                . 'Diese Migration lässt sich nicht zurücknehmen, ohne Platzierungen zu erfinden.'
            );
        }

        Schema::table('results', function (Blueprint $table) {
            $table->integer('placement')->change();
        });
    }
};
