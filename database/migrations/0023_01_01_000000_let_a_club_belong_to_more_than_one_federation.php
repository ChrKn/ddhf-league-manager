<?php

use App\Federations\FederationKind;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A club may belong to more than one federation.
 *
 * `groups.federation_id` held a single reference, which is enough for every German club and wrong
 * for the ones that also belong to a school network. INDES Kulmbach is in the DDHF and in INDES;
 * INDES Salzburg is in the ÖFHF and in INDES. Neither fits in one column.
 *
 * `federations` gains a kind alongside it, because the two things that table now holds are not
 * alike - see App\Federations\FederationKind. Everything on record today is a national federation,
 * so that is the default and nothing has to be sorted through by hand.
 *
 * Nothing about a standing moves. `results.federation_id` is a snapshot taken when the result was
 * written and is not touched here, so the 1870 rows that exist keep saying exactly what they said.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('federations', function (Blueprint $table) {
            $table->string('kind', 20)->default(FederationKind::National->value)->after('name');
        });

        Schema::create('federation_group', function (Blueprint $table) {
            $table->id();
            $table->foreignId('federation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            // One membership per pair. Saying it twice is not a stronger membership.
            $table->unique(['federation_id', 'group_id']);
        });

        $now = now();

        foreach (DB::table('groups')->whereNotNull('federation_id')->get(['id', 'federation_id']) as $group) {
            DB::table('federation_group')->insert([
                'group_id'      => $group->id,
                'federation_id' => $group->federation_id,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
        }

        Schema::table('groups', function (Blueprint $table) {
            $table->dropConstrainedForeignId('federation_id');
        });
    }

    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->foreignId('federation_id')->nullable()->after('country')->constrained()->nullOnDelete();
        });

        // Only a national federation can go back in the column - an association was never what it
        // meant. A club with two of them keeps the first, which is the best a single column can do
        // and the reason this migration exists.
        $memberships = DB::table('federation_group')
            ->join('federations', 'federations.id', '=', 'federation_group.federation_id')
            ->where('federations.kind', FederationKind::National->value)
            ->orderBy('federation_group.id')
            ->get(['federation_group.group_id', 'federation_group.federation_id']);

        foreach ($memberships as $membership) {
            DB::table('groups')
                ->where('id', $membership->group_id)
                ->whereNull('federation_id')
                ->update(['federation_id' => $membership->federation_id]);
        }

        Schema::dropIfExists('federation_group');

        Schema::table('federations', function (Blueprint $table) {
            $table->dropColumn('kind');
        });
    }
};
