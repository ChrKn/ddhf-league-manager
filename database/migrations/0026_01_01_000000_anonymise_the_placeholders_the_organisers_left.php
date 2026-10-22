<?php

use App\Import\NameMatcher;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A starter the organisers had already anonymised is anonymised here as well.
 *
 * Until now such a row was imported as a fencer literally called "Anonymer Fechter" - a stored
 * name that happens to read like the placeholder, with no anonymisation date behind it. Everything
 * that asks the flag took it for a person: the JSON said `anonymized: false` next to that name, and
 * the pages set it like any other. From here on the flag is the only thing that says so, the way it
 * already is for somebody anonymised through the panel.
 *
 * The placeholders that carry a result lose their name and are marked, dated from when they were
 * created - the moment from which they stood for nobody. The ones without a result are left over
 * from imports that were taken back, and go: there is no identity in them to keep.
 *
 * And the review rows get a box of their own for it. "create_inactive" mixed two questions into
 * one answer - who this is, and whether they may be named - and it is the second one that was
 * missed. Rows that already said so keep saying so.
 *
 * No way back. Nothing removed here was a name.
 */
return new class extends Migration
{
    private const PLACEHOLDER = 'Anonymer Fechter';

    private const REASON = 'Vom Veranstalter anonymisiert';

    public function up(): void
    {
        Schema::table('result_import_rows', function (Blueprint $table) {
            $table->boolean('anonymize')->default(false)->after('action');
        });

        DB::table('result_import_rows')
            ->where('action', 'create_inactive')
            ->update(['action' => 'create', 'anonymize' => true]);

        $placeholder = NameMatcher::normalize(self::PLACEHOLDER);

        $fencers = DB::table('fencers')
            ->whereNull('anonymized_at')
            ->get(['id', 'first_name', 'last_name', 'created_at'])
            ->filter(fn ($fencer) => NameMatcher::normalize(
                trim(($fencer->first_name ?? '') . ' ' . ($fencer->last_name ?? ''))
            ) === $placeholder);

        foreach ($fencers as $fencer) {
            $results = DB::table('results')->where('fencer_id', $fencer->id);

            if (!$results->exists()) {
                DB::table('fencers')->where('id', $fencer->id)->delete();

                continue;
            }

            // The same fields AnonymizeFencerAction::anonymize() clears, written here directly so
            // that this migration does not depend on how the panel does it later.
            DB::table('fencers')->where('id', $fencer->id)->update([
                'title'                => null,
                'first_name'           => null,
                'last_name'            => null,
                'birth_name'           => null,
                'nationality'          => null,
                'date_of_birth'        => null,
                'gender'               => null,
                'group_id'             => null,
                'is_active'            => false,
                'anonymized_at'        => $fencer->created_at,
                'anonymization_reason' => self::REASON,
            ]);

            $results->update(['group_id' => null, 'fencer_group_name' => null]);
        }
    }

    public function down(): void
    {
        Schema::table('result_import_rows', function (Blueprint $table) {
            $table->dropColumn('anonymize');
        });
    }
};
