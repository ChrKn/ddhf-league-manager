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
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_active')->default(true);
            $table->string('public_id', 6)->unique();
            $table->string('name', 250);
            $table->string('abbreviation', 10)->nullable();
            $table->string('website_url', 250)->nullable();
            $table->string('logo_url', 250)->nullable();
            $table->foreignId('federation_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('groups');
    }
};
