<?php

use App\Import\GroupResolver;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Holds the club someone fenced for at the result instead of only at the person.
 *
 * A standing is a DDHF ranking, so it may only list fencers whose club was a member at the time
 * of the event. Membership is not recorded over time - groups.federation_id and fencers.group_id
 * are each a single present-day value - so asking the person would answer the wrong question: a
 * club change would rewrite every past placement, which is exactly the fault the old WordPress
 * site had.
 *
 * results.fencer_group_name already keeps the club as the source file spelled it. That name is
 * resolved here once and written down as an id, so the standing can filter on it.
 */
return new class extends Migration
{
    /**
     * The club of these results was never recorded, but their federation is known from the old
     * WordPress data. Keyed by fencer and tournament so the rows stay identifiable.
     *
     * @var list<array{fencer: string, tournament: string, federation: string, why: string}>
     */
    private const KNOWN_WITHOUT_A_CLUB = [
        [
            'fencer'     => '', // public_id
            'tournament' => '', // public_id
            'federation' => 'DDHF',
            'why'        => 'Description of why the club was not recorded',
        ],
    ];

    public function up(): void
    {
        Schema::table('results', function (Blueprint $table) {
            $table->foreignId('group_id')->nullable()->after('fencer_id')->constrained()->nullOnDelete();
            $table->foreignId('federation_id')->nullable()->after('group_id')->constrained()->nullOnDelete();
        });

        $this->resolveTheRecordedClubNames();
        $this->applyTheKnownExceptions();
    }

    public function down(): void
    {
        Schema::table('results', function (Blueprint $table) {
            $table->dropConstrainedForeignId('federation_id');
            $table->dropConstrainedForeignId('group_id');
        });
    }

    /**
     * Turns every recorded club name into an id.
     *
     * Only an exact name or a recorded alias counts, the same bar the import applies. A
     * similarity is a suggestion and wants a person looking at it, so anything below that stops
     * the migration rather than guessing at a fencer's federation.
     */
    private function resolveTheRecordedClubNames(): void
    {
        $names = DB::table('results')
            ->whereNotNull('fencer_group_name')
            ->where('fencer_group_name', '<>', '')
            ->distinct()
            ->orderBy('fencer_group_name')
            ->pluck('fencer_group_name');

        if ($names->isEmpty()) {
            return;
        }

        $resolver = new GroupResolver();
        $resolved = [];
        $unresolved = [];

        foreach ($names as $name) {
            $suggestion = $resolver->resolve($name);

            if ($suggestion->confidence->isCertain()) {
                $resolved[$name] = $suggestion->record;
            } else {
                $unresolved[] = $name . ' (' . $suggestion->confidence->label() . ')';
            }
        }

        if ($unresolved !== []) {
            throw new RuntimeException(
                "Diese Vereinsnamen aus den Ergebnissen lassen sich nicht sicher zuordnen. Bitte "
                . "einen Alias eintragen oder den Verein anlegen, dann erneut migrieren:\n  - "
                . implode("\n  - ", $unresolved)
            );
        }

        foreach ($resolved as $name => $group) {
            DB::table('results')
                ->where('fencer_group_name', $name)
                ->update([
                    'group_id'      => $group->id,
                    'federation_id' => $group->federation_id,
                ]);
        }
    }

    /** Results whose club is unknown but whose federation is not. */
    private function applyTheKnownExceptions(): void
    {
        foreach (self::KNOWN_WITHOUT_A_CLUB as $exception) {
            $federation = DB::table('federations')
                ->where('abbreviation', $exception['federation'])
                ->value('id');

            $fencer = DB::table('fencers')->where('public_id', $exception['fencer'])->value('id');
            $tournament = DB::table('tournaments')->where('public_id', $exception['tournament'])->value('id');

            if (!$federation || !$fencer || !$tournament) {
                continue;
            }

            DB::table('results')
                ->where('fencer_id', $fencer)
                ->where('tournament_id', $tournament)
                ->whereNull('federation_id')
                ->update(['federation_id' => $federation]);
        }
    }
};
