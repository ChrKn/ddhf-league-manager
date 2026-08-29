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
        Schema::create('fencers', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_active')->default(true);
            $table->string('public_id', 10)->unique();
            $table->enum('title', ['doctor', 'professor'])->nullable();
            $table->string('first_name');
            $table->string('last_name');
            $table->char('nationality', 2)->nullable();
            $table->foreignId('group_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['male', 'female', 'non_binary'])->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fencers');
    }
};
