<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The import stops being a pair of console commands with a csv between them.
 *
 * The csv was the state: it held what had been matched and what somebody had decided about it,
 * and it lived on whoever's machine had run the first command. Three tables put that state where
 * both the panel and the console can reach it, and where an interrupted review can be picked up
 * the next day.
 *
 * None of this is data a standing is computed from. An import writes into results only at the
 * moment it is applied; up to then it is entirely self-contained, which is what makes abandoning
 * one cost nothing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('result_imports', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 8)->unique();
            // Who is doing this. Nullable because a console run has no account behind it, and
            // because an import outlives the person who leaves the federation.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->index();
            // Which shape the files were read in. Stored so a file can be read again the same
            // way, once there is more than one format.
            $table->string('format', 40);
            $table->boolean('remember_aliases')->default(false);
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
        });

        Schema::create('result_import_sheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('result_import_id')->constrained()->cascadeOnDelete();
            // The name it was sent under. The stored file is named by the system, so this is the
            // only thing that still ties a row back to the file a person recognises.
            $table->string('original_name', 250);
            $table->string('stored_path', 500);
            // Where it is headed. The tournament is created from these two when the import is
            // applied - not before, so that an abandoned review leaves no empty tournament.
            $table->foreignId('season_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('tournament_id')->nullable()->constrained()->nullOnDelete();
            // What the file says about itself.
            $table->unsignedInteger('participants')->nullable();
            $table->string('format', 100)->nullable();
            // A field recorded only in part, as the 2023 Ochsenstich was. The stated size still
            // has to be right, because the points hang on it; only the row count is let go.
            $table->boolean('partial')->default(false);
            $table->timestamps();
        });

        Schema::create('result_import_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('result_import_sheet_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sheet_row');
            // What the file said, kept as written. The reviewer has to be able to see the source
            // next to what was made of it.
            $table->string('placement', 100);
            $table->string('name', 250);
            $table->string('club', 250)->nullable();
            $table->string('federation', 250)->nullable();
            // What was made of it. Null means nothing was found and nothing chosen.
            $table->foreignId('fencer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('fencer_confidence', 20)->nullable();
            $table->decimal('fencer_score', 4, 3)->nullable();
            $table->foreignId('group_id')->nullable()->constrained()->nullOnDelete();
            $table->string('group_confidence', 20)->nullable();
            $table->decimal('group_score', 4, 3)->nullable();
            // What is to happen. Empty until somebody decides, which is the whole point of the
            // review step.
            $table->string('action', 20)->nullable();
            $table->string('note', 250)->nullable();
            // What came of it, so an applied import says which row became which result.
            $table->foreignId('result_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['result_import_sheet_id', 'sheet_row']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('result_import_rows');
        Schema::dropIfExists('result_import_sheets');
        Schema::dropIfExists('result_imports');
    }
};
