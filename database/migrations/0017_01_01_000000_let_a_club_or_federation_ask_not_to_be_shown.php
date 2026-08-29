<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether a club or a federation may be named where the public can read it.
 *
 * This cannot ride on is_active, which already means something else and something factual: a
 * club that is not active has dissolved or stopped fencing, and its past results still name it.
 * Asking not to be shown says nothing about whether the club exists.
 *
 * It is also not anonymisation. No personal data is involved - a club is a group, not a person -
 * so nothing is deleted and no attribute is cleared. The record keeps everything it had, every
 * result keeps pointing at it, and the standings are computed from exactly the same rows as
 * before. Only the places a reader can see stop saying the name.
 *
 * What stays visible on purpose: the fencers. Somebody who fenced for a club that later asked
 * not to be named keeps their placement and their points, because those are theirs rather than
 * the club's; their row simply shows no club. Removing the people would be a different and much
 * larger claim than the one being made.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['groups', 'federations'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                // Everything on record today was entered to be shown, so the default is true and
                // no existing row changes.
                $blueprint->boolean('is_public')->default(true)->after('is_active');
            });
        }
    }

    public function down(): void
    {
        foreach (['groups', 'federations'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('is_public');
            });
        }
    }
};
