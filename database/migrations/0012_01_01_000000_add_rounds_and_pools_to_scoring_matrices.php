<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Teaches the scoring matrices the other two readings of their own columns.
 *
 * The official points table is one grid with three headers stacked on top of each other -
 * "Platzierung / Runde / Finale". The column headed "7-8" is also "8er Runde" and also
 * "4tel Finale"; they are not three columns, they are three ways of arriving at one. A thirteenth
 * column, "bzw. Pools", stands apart and is worth one point at every field size.
 *
 * Only the placement reading was ever stored, so a bracket result had to be bent into a placement
 * to find its column. That worked, and it still lied: whoever lost in the round of sixteen was
 * written down as sixteenth even when they were ninth. Worse, a fencer knocked out in the pools of
 * a large field kept their pool rank and collected two or three points for a column they never
 * reached.
 *
 * "round" therefore goes onto the entries that already exist rather than into a list of its own -
 * it is the same column under its second name, and two lists would be free to drift apart. "pools"
 * sits on the participant band because that is where the table puts it. A band that scores nothing
 * scores nothing for a pool exit either, which is why the smallest one gets a zero.
 *
 * Only the four matrices laid out like the graphic are touched. The 2019 to 2021 ones have a
 * different set of columns and no source describing their rounds - and no result of those years
 * carries anything but a rank, so nothing asks them.
 *
 * The same values are in database/scoring-matrices/*.json, which is where they are read by eye.
 */
return new class extends Migration
{
    /** The round each placement column of the graphic is also headed with. */
    private const ROUND_FOR_PLACE = [
        5  => 'last-6',
        7  => 'last-8',
        9  => 'last-12',
        13 => 'last-16',
        17 => 'last-24',
        25 => 'last-32',
        33 => 'last-48',
        49 => 'last-64',
    ];

    /**
     * Punkteschlüssel 2022+, Langes Schwert Offen 2022, and their two 2026 successors. The 2026
     * pair reaches past 129 entrants, where the graphic stops - those bands keep their placement
     * reading only, and the open question is in the readme.
     */
    private const MATRICES = ['L5KV', '3B4Z', 'G55A', 'WRNF'];

    public function up(): void
    {
        $this->rewrite(function (array $rule): array {
            $rule['pools'] = ($rule['participants']['min'] ?? 0) === 0 ? 0 : 1;

            foreach ($rule['points'] as $index => $entry) {
                $place = $entry['place']['min'] ?? 0;

                if (isset(self::ROUND_FOR_PLACE[$place])) {
                    $rule['points'][$index]['round'] = self::ROUND_FOR_PLACE[$place];
                }
            }

            return $rule;
        });
    }

    public function down(): void
    {
        $this->rewrite(function (array $rule): array {
            unset($rule['pools']);

            foreach ($rule['points'] as $index => $entry) {
                unset($rule['points'][$index]['round']);
            }

            return $rule;
        });
    }

    private function rewrite(callable $change): void
    {
        foreach (DB::table('scoring_matrices')->whereIn('public_id', self::MATRICES)->get() as $matrix) {
            $rules = json_decode($matrix->matrix, true);

            if (!is_array($rules)) {
                continue;
            }

            DB::table('scoring_matrices')
                ->where('id', $matrix->id)
                ->update(['matrix' => json_encode(array_map($change, $rules))]);
        }
    }
};
