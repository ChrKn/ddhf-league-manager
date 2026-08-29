<?php

use App\Models\Fencer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lets an anonymised fencer hold no name at all, and takes the club off their results.
 *
 * Anonymising used to write "Anonymer Fechter" into the name columns, because they could not be
 * empty. That put a sentence about a person exactly where the person used to be: the placeholder
 * could be edited, exported and matched against like any other name, and the import has already
 * piled unrelated rows onto one such record. The placeholder is something to show, so it belongs
 * in the code - see Fencer::getDisplayNameAttribute - and the record itself keeps nothing.
 *
 * What stays on the result is the placement and the federation. Both are facts about the
 * competition rather than about the person: the placement is what happened, the federation is
 * what puts it in a standing. The club goes, in both spellings, because in a small club the club
 * plus a tournament plus a placement is enough to work backwards to a name.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fencers', function (Blueprint $table) {
            $table->string('first_name')->nullable()->change();
            $table->string('last_name')->nullable()->change();
        });

        // Records anonymised under the old rule still carry the placeholder and, possibly, a club
        // on their results. Clearing them here rather than asking anybody to redo the deletion by
        // hand: a half applied rule is what makes a record recognisable again.
        $anonymized = DB::table('fencers')->whereNotNull('anonymized_at')->pluck('id');

        DB::table('fencers')
            ->whereIn('id', $anonymized)
            ->update(['first_name' => null, 'last_name' => null]);

        DB::table('results')
            ->whereIn('fencer_id', $anonymized)
            ->update(['group_id' => null, 'fencer_group_name' => null]);
    }

    public function down(): void
    {
        // The columns cannot go back to NOT NULL while the anonymised records are empty, so the
        // placeholder is written back the way it used to be stored. What the results lost stays
        // lost, which is rather the point of having removed it.
        [$first_name, $last_name] = explode(' ', Fencer::ANONYMOUS_NAME, 2);

        DB::table('fencers')->whereNull('first_name')->update(['first_name' => $first_name]);
        DB::table('fencers')->whereNull('last_name')->update(['last_name' => $last_name]);

        Schema::table('fencers', function (Blueprint $table) {
            $table->string('first_name')->nullable(false)->change();
            $table->string('last_name')->nullable(false)->change();
        });
    }
};
