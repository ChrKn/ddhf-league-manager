<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When a tournament was fenced, where that is not simply when the event was.
 *
 * A tournament has taken its date from its event so far, which is right for most of them and
 * wrong for the ones that matter: 26 of 45 events here run over more than one day, several with
 * six tournaments across them. Sabre on the Saturday and longsword on the Sunday are two dates,
 * not one, and a preliminary round on the first day with the final on the second is a span.
 *
 * Both columns are nullable and stay empty for every tournament on record. The event remains the
 * fallback, so nothing changes until somebody knows better and says so - which is the only way
 * to add this without inventing dates for 86 tournaments nobody looked up.
 *
 * Dates, not timestamps. Nobody has recorded a time of day and nobody has asked to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->date('start_date')->nullable()->after('region');
            $table->date('end_date')->nullable()->after('start_date');
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn(['start_date', 'end_date']);
        });
    }
};
