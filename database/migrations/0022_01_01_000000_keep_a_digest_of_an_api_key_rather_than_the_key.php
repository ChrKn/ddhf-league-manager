<?php

use App\Models\ApiKey;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The keys stop being readable.
 *
 * They were stored as they were handed out, which meant that anybody who came by the table came by
 * working keys - and this table travels more than most, because a dump is how the data gets moved
 * around. In their place goes a digest that cannot be turned back into a key, plus the first eight
 * characters so that a row is still recognisable to whoever made it.
 *
 * The keys that exist keep working: their plaintext is right here while this runs, so it is hashed
 * in passing rather than thrown away. Nothing has to be handed out again, and the key in test.http
 * goes on opening the local API.
 *
 * Rolling back is where the one-way street shows. The column comes back empty, because the
 * plaintext is gone by then and this migration cannot invent it. Every key would have to be issued
 * anew.
 */
return new class extends Migration
{
    private const PREFIX_LENGTH = 8;

    public function up(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->char('key_hash', 64)->nullable()->after('name');
            $table->char('key_prefix', self::PREFIX_LENGTH)->nullable()->after('key_hash');
        });

        foreach (DB::table('api_keys')->get(['id', 'key']) as $row) {
            DB::table('api_keys')->where('id', $row->id)->update([
                'key_hash'   => ApiKey::digest($row->key),
                'key_prefix' => substr($row->key, 0, self::PREFIX_LENGTH),
            ]);
        }

        // The index that made the plaintext searchable goes first and on its own. MySQL would drop
        // it along with the column, SQLite leaves it behind pointing at nothing - and the test
        // suite runs on SQLite.
        Schema::table('api_keys', function (Blueprint $table) {
            $table->dropUnique(['key']);
        });

        Schema::table('api_keys', function (Blueprint $table) {
            $table->dropColumn('key');
        });

        Schema::table('api_keys', function (Blueprint $table) {
            $table->char('key_hash', 64)->nullable(false)->change();
            $table->char('key_prefix', self::PREFIX_LENGTH)->nullable(false)->change();
            $table->unique('key_hash');
        });
    }

    public function down(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            // Nullable, unlike the column it replaces: there is nothing to put in it. Several rows
            // may therefore sit here empty, which a unique index over a nullable column permits.
            $table->char('key', 64)->nullable()->after('name');
            $table->unique('key');
        });

        Schema::table('api_keys', function (Blueprint $table) {
            $table->dropUnique(['key_hash']);
        });

        Schema::table('api_keys', function (Blueprint $table) {
            $table->dropColumn(['key_hash', 'key_prefix']);
        });
    }
};
