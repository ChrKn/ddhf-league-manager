<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Undoes the ten club names the old name mutator mangled.
 *
 * It turned every hyphen into an en dash, including the ones glued between characters, where a
 * hyphen joins words and means something else: Neu-Ulm, Grün-Weiß, ARMA-PL. App\Traits\
 * NameNormalization now only converts a dash that stands alone between spaces, so these can be
 * written back and will stay written back - until that change, correcting one by hand turned it
 * into an en dash again on save.
 *
 * Only glued dashes are touched. The nineteen names where the en dash stands between spaces are
 * correct and stay as they are.
 *
 * This cannot affect matching. NameMatcher::normalize turns every run of non-alphanumeric
 * characters into a single space, so a hyphen and an en dash have always been the same character
 * to the club resolver, the alias table and the import. Measured over all 223 clubs before this
 * was written: the normalized form of every name is identical either way.
 *
 * Aliases are left alone on purpose. They record how a source file spelled a club, they are only
 * ever matched through normalize, and rewriting them would mean editing somebody's spelling of
 * their own name for no effect.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->rewrite('–', '-');
    }

    public function down(): void
    {
        $this->rewrite('-', '–');
    }

    /**
     * Swaps a dash for another one, but only where it is glued between two non-spaces.
     *
     * Through the query builder rather than the model: the mutator would run on save and this
     * migration is precisely about what the mutator did.
     */
    private function rewrite(string $from, string $to): void
    {
        foreach (DB::table('groups')->get(['id', 'name']) as $group) {
            $fixed = preg_replace(
                '/(?<=\S)' . preg_quote($from, '/') . '(?=\S)/u',
                $to,
                $group->name,
            );

            if ($fixed !== $group->name) {
                DB::table('groups')->where('id', $group->id)->update(['name' => $fixed]);
            }
        }
    }
};
