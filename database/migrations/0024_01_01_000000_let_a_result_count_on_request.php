<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records that the federation let one result count although the club was not a member yet.
 *
 * The Kulanzregelung: on application, the last DDHF tournament somebody took part in before their
 * club joined can be counted. It is granted case by case and never automatically - the one thing
 * that decides it, whether an application was made and approved, is nowhere in the data.
 *
 * Deliberately not done by writing the federation onto the result. That column is a snapshot of who
 * the club belonged to on the day it was fenced, and back-filling it would state a membership that
 * did not exist. It would also be indistinguishable from an ordinary one, so the next person
 * checking the standing against the members list would find a discrepancy with nothing to explain
 * it. An exception should look like an exception.
 *
 * One nullable column rather than a flag plus a note, so that a granted exception can never stand
 * there without saying who granted it and on what grounds.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('results', function (Blueprint $table) {
            $table->string('counted_on_request', 250)->nullable()->after('federation_id');
        });
    }

    public function down(): void
    {
        Schema::table('results', function (Blueprint $table) {
            $table->dropColumn('counted_on_request');
        });
    }
};
