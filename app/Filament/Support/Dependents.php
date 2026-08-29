<?php

namespace App\Filament\Support;

use App\Models\Discipline;
use App\Models\Division;
use App\Models\Event;
use App\Models\Federation;
use App\Models\Fencer;
use App\Models\Group;
use App\Models\Result;
use App\Models\Ruleset;
use App\Models\ScoringMatrix;
use App\Models\Season;
use App\Models\Standing;
use App\Models\Tournament;
use Illuminate\Database\Eloquent\Model;

/**
 * What points at a record, and therefore what stops it being deleted.
 *
 * Deliberately one file rather than a line in each resource: this list has to be readable against
 * the database's own foreign keys in one sitting, because that is the only way to notice one going
 * unguarded. Measured against `information_schema` rather than remembered.
 *
 * Two kinds of reference matter, for opposite reasons.
 *
 * **RESTRICT** — the database already refuses, but as a raw SQL error that tells whoever clicked
 * nothing. Guarding them turns that into a sentence: fencers, tournaments, seasons, standings,
 * disciplines, divisions, events, scoring matrices.
 *
 * **SET NULL** — the database lets it through, and that is where the holes are. Deleting a
 * federation empties `results.federation_id` for every result ever fenced for it, and
 * StandingCalculator scores nobody whose result does not name our own federation, so one deleted
 * row empties every standing. Deleting a club strips the club off historical results. Deleting a
 * ruleset drops results out of a ruleset-mode standing. All three silently.
 *
 * What is deliberately *not* counted is a record's own subordinate data — a club's aliases,
 * locations and memberships, a fencer's ranking choices. Those are meant to go with it, and the
 * database says so by cascading them.
 */
class Dependents
{
    /**
     * Label, in German, => how many there are.
     *
     * @return array<string, \Closure(Model): int>
     */
    public static function for(Model $record): array
    {
        return match (true) {
            // SET NULL into results: the row that empties every standing.
            $record instanceof Federation => [
                'Ergebnisse' => fn (Federation $f): int => Result::where('federation_id', $f->id)->count(),
                'Vereine'    => fn (Federation $f): int => $f->groups()->count(),
            ],

            // SET NULL into results and fencers. Counted through the query rather than a relation
            // because Group has no results() - a result points at the club it was fenced for,
            // which is not the same question as which club somebody is in today.
            $record instanceof Group => [
                'Ergebnisse' => fn (Group $g): int => Result::where('group_id', $g->id)->count(),
                'Fechter'    => fn (Group $g): int => $g->fencers()->count(),
            ],

            // SET NULL into tournaments: changes what a ruleset-mode standing adds up.
            $record instanceof Ruleset => [
                'Turniere' => fn (Ruleset $r): int => Tournament::where('ruleset_id', $r->id)->count(),
            ],

            // The rest are RESTRICT. The database refuses them anyway; this says why.
            $record instanceof Fencer => [
                'Ergebnisse' => fn (Fencer $f): int => $f->results()->count(),
            ],

            $record instanceof Tournament => [
                'Ergebnisse' => fn (Tournament $t): int => $t->results()->count(),
            ],

            $record instanceof Season => [
                'Turniere' => fn (Season $s): int => $s->tournaments()->count(),
            ],

            $record instanceof Standing => [
                'Saisons' => fn (Standing $s): int => Season::where('standing_id', $s->id)->count(),
            ],

            $record instanceof Discipline => [
                'Ranglisten' => fn (Discipline $d): int => Standing::where('discipline_id', $d->id)->count(),
            ],

            $record instanceof Division => [
                'Ranglisten' => fn (Division $d): int => Standing::where('division_id', $d->id)->count(),
            ],

            $record instanceof Event => [
                'Turniere'  => fn (Event $e): int => Tournament::where('event_id', $e->id)->count(),
                'Ausrichter' => fn (Event $e): int => $e->organizers()->count(),
            ],

            $record instanceof ScoringMatrix => [
                'Saisons' => fn (ScoringMatrix $m): int => Season::where('scoring_matrix_id', $m->id)->count(),
            ],

            // Everything else is working material or a leaf: API keys, imports, a club's aliases
            // and locations. Deleting those is a correction rather than a hole.
            default => [],
        };
    }
}
