<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether a season may be shown where the public can read it.
 *
 * The site goes live with the current year, and the older ones follow one by one as they have been
 * checked against what was published at the time. Until then a season is held back: not on the
 * site, not in the API and not in the search.
 *
 * Like the flag on clubs and federations, this changes what is shown and nothing else. The results
 * stay, and a season that is held back still takes part in what other seasons compute from it -
 * most of all the category choice, which looks across the sibling seasons of the same year. A rank
 * must not change because a neighbouring table has not been released yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seasons', function (Blueprint $table) {
            // Everything on record today was entered to be shown, so no existing row changes.
            $table->boolean('is_public')->default(true)->after('year');
        });
    }

    public function down(): void
    {
        Schema::table('seasons', function (Blueprint $table) {
            $table->dropColumn('is_public');
        });
    }
};
