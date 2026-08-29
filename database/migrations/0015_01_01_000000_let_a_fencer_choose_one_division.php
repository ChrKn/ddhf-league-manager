<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records which of two standings a fencer is ranked in.
 *
 * The same person can hold results in *Damen+* and in *offen* of one weapon and year.
 *
 * It is deliberately not read off `fencers.gender`. Women may enter the open category at any time
 * and be ranked there, men can never be ranked in Damen+, so the two questions do not have the same
 * answer and must not share a column.
 *
 * A row means: this fencer is ranked in this standing. The season already names discipline,
 * division and year, so the link *is* the choice - a second column naming the division would be a
 * second version of the same fact.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fencer_season', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fencer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['fencer_id', 'season_id']);
        });

        Schema::table('seasons', function (Blueprint $table) {
            $table->boolean('division_choice_required')->default(false)->after('scoring_mode');
        });
    }

    public function down(): void
    {
        Schema::table('seasons', function (Blueprint $table) {
            $table->dropColumn('division_choice_required');
        });

        Schema::dropIfExists('fencer_season');
    }
};
