<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Alternative spellings under which a club shows up in imported files.
 *
 * Similarity cannot bridge every gap: "ESK Augsburg" and "ESK Starnberg" are both the club
 * "Europäische Schwertkunst", and no edit distance will ever discover that. Once someone has
 * decided such a case during an import, it is recorded here and the next import resolves it
 * without asking again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->string('alias');
            $table->timestamps();

            // An alias points at exactly one club, otherwise it would not resolve anything.
            $table->unique('alias');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_aliases');
    }
};
