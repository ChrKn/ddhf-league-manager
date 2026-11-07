<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Announcements on the public site - first of all that a result was corrected, and why a table
 * looks different from the one somebody printed last week.
 *
 * Two dates are public and one is not. created_at is the date a reader sees. revised_at is the
 * "updated on" next to it, and it is only ever set on purpose: fixing a typo is not news, and a
 * date that moves on every save would announce changes nobody made. updated_at stays what it is
 * everywhere else, a technical stamp.
 *
 * The author is for the panel only and is never shown. It points at a user rather than holding a
 * name, and is kept when that user goes - the announcement stays, just without an author.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            // Optional. Where there is none, the English site shows the German text.
            $table->string('title_en')->nullable();
            $table->text('body_en')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('revised_at')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
