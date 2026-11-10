<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Three kinds of somebody on a start list, and the record told only one of them apart.
 *
 * Fenced to the end: a result, part of the field. Fenced and gave up - injured, or simply stopped:
 * still a result at the place they went out on, still part of the field, and so far written exactly
 * like the first. Registered and never fenced: no result and no part of the field, and so far not
 * written at all, so a field of 22 out of 25 registrations looked no different from a field of 22
 * that nobody else had wanted to fence in.
 *
 * tournaments.registered_count says how many had registered when registration closed. It is
 * evidence, nothing more - for a decision to score a tournament that fell short of the threshold
 * because people cancelled. It is never part of a point: participant_count stays the field, and a
 * number that a longer registration list could raise has no business moving anybody into a bigger
 * column. Nobody who did not fence gets a record, so there is no name and no reason - "krank" is
 * health data - only the difference between the two numbers.
 *
 * results.withdrew marks the second kind. It changes nothing about the result or the field; it is
 * there so the list can say "Aufgabe" instead of leaving a reader to wonder why a favourite went
 * out in the pools.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->unsignedInteger('registered_count')->nullable()->after('participant_count');
        });

        Schema::table('results', function (Blueprint $table) {
            $table->boolean('withdrew')->default(false)->after('placement');
        });
    }

    public function down(): void
    {
        Schema::table('results', function (Blueprint $table) {
            $table->dropColumn('withdrew');
        });

        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn('registered_count');
        });
    }
};
