<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks a fencer whose personal data has been removed on request.
 *
 * This cannot ride on is_active: that flag means "does not compete any more", and someone who
 * simply stopped fencing must keep their entries in the standings of past seasons. The name
 * cannot carry it either, because a name is display text that varies with whoever typed it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fencers', function (Blueprint $table) {
            // Also records when the request was honoured, which is the part that matters when
            // someone asks about it later.
            $table->timestamp('anonymized_at')->nullable()->after('gender');

            // Internal note only. Never leaves the database - see FencerResource and
            // FencerExporter.
            $table->string('anonymization_reason')->nullable()->after('anonymized_at');
        });
    }

    public function down(): void
    {
        Schema::table('fencers', function (Blueprint $table) {
            $table->dropColumn(['anonymized_at', 'anonymization_reason']);
        });
    }
};
