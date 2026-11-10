<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The smallest field a season scores, as a rule of the season rather than a shape of its matrix.
 *
 * The official DDHF points table is one table. Three of its columns - 5, 9 and 17 - carry the
 * remark "gilt nur für nicht offen Langschwert", and that remark is all that separates the open
 * longsword standing from every other one. Until now the system could only say it by keeping a
 * second matrix with those three columns cut out, which made the threshold invisible: it was
 * wherever the first column that awards anything happened to start. A tournament the federation
 * wants scored anyway then had nowhere to go but a third matrix.
 *
 * So the threshold becomes a number on the season, and the matrix goes back to being the table.
 * Null is how every season stands today and keeps them exactly as they are: the threshold is then
 * whatever the matrix implies, as before. Nothing is moved onto another matrix here - which season
 * uses which table is the federation's to change in the panel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seasons', function (Blueprint $table) {
            $table->unsignedInteger('min_participants')->nullable()->after('scoring_mode');
        });
    }

    public function down(): void
    {
        Schema::table('seasons', function (Blueprint $table) {
            $table->dropColumn('min_participants');
        });
    }
};
