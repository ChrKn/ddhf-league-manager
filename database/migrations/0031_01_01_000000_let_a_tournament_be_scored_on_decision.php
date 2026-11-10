<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets the federation score a tournament that fell short of the season's threshold.
 *
 * The case it was built for: open longsword, 25 registrations, three cancellations after
 * registration closed, 22 who fenced - below the 25 the season scores from. The federation wants it
 * scored, and wants that to be a record in the system people can look up rather than a correction
 * somebody makes on the side.
 *
 * The decision lifts the season's threshold and nothing else. Points still come from the season's
 * matrix and at the field that fenced - 22, never the 25 registered - and the matrix's own floor
 * stays where it is: no decision turns a field of three into points where the table has no column
 * for it. There is deliberately no matrix of its own on the tournament; one table per season is
 * what makes two tournaments of a season comparable.
 *
 * Shaped after results.counted_on_request, the Kulanzregelung: the column holds the grounds rather
 * than a yes, so a decision can never stand there without saying why it was made. Next to it the
 * day it was taken, which the panel requires once there are grounds, and optionally the
 * announcement that made it public. Set null when that announcement goes: the decision stands, it
 * just no longer points anywhere.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->text('scored_on_decision')->nullable()->after('registered_count');
            $table->date('decided_on')->nullable()->after('scored_on_decision');
            $table->foreignId('announcement_id')->nullable()->after('decided_on')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('announcement_id');
            $table->dropColumn(['scored_on_decision', 'decided_on']);
        });
    }
};
