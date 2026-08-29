<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records how a tournament was fenced, because it decides how its placements are to be read.
 *
 * The DDHF rule for 2024 says points come from "the final you reach" in a knockout, from "the
 * placement" in an all-against-all, and from "the round" in a double knockout. So a placement in
 * a bracket tournament is the worst rank of the round someone went out in - four quarter
 * finalists all stand on place 8 - while a placement in a pool tournament is the rank itself.
 *
 * Both now occur in the same season. Without the format written down, the placement column of a
 * bracket tournament looks like a recording error, and nothing in the database says otherwise.
 *
 * Free text on purpose: the value comes from the "Turniersystem" column of the submission
 * template, which organisers fill in their own words. Older tournaments carry nothing, and that
 * is honest - nobody wrote it down for them.
 *
 * Named "format" rather than "system" because SYSTEM is a reserved word in MySQL 8: Eloquent
 * quotes it, but the first raw query written against the column would fail.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->string('format', 120)->nullable()->after('region');
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn('format');
        });
    }
};
