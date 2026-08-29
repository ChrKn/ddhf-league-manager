<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('seasons', function (Blueprint $table) {
            // Stored as a string rather than an enum: the evaluation systems are still being
            // worked out, and adding one should not require a schema change.
            $table->string('scoring_mode', 20)->default('standard')->after('scoring_matrix_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('seasons', function (Blueprint $table) {
            $table->dropColumn('scoring_mode');
        });
    }
};
