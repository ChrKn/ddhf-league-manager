<?php

namespace App\Import;

use App\Models\Fencer;
use App\Models\Group;
use App\Models\Result;
use App\Models\Tournament;
use App\Standings\Placement;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The second half of an import: take reviewed rows and write them.
 *
 * An import brings in what is new. It never changes a result that is already there - a wrong
 * placement is a correction, and corrections are a separate job with a separate form, so that
 * whoever is at the screen can tell which of the two they are doing.
 *
 * Everything happens inside one transaction, so a set of rows that turns out to be inconsistent
 * halfway through leaves nothing behind.
 */
class ImportWriter
{
    /** What the "aktion" column of a reviewed row may say. */
    public const ACTIONS = ['use', 'create', 'create_inactive', 'skip'];

    /**
     * The same four, said in a way somebody choosing between them can act on.
     *
     * Every one of them names the fencer, because that is what three of them decide and what the
     * fourth skips a whole row over. The club is not among them: it is used when one is picked and
     * created from the file's spelling when none is, and saying "neu anlegen" in two columns that
     * mean different things is exactly how this got read as being about the club.
     */
    public const ACTION_LABELS = [
        'use'             => 'Diesen Fechter verwenden',
        'create'          => 'Fechter neu anlegen',
        'create_inactive' => 'Fechter neu anlegen, deaktiviert',
        'skip'            => 'Zeile überspringen',
    ];

    /** @var array<string, Fencer> normalized name => fencer created during this run */
    private array $createdThisRun = [];

    private GroupResolver $groups;

    private FencerResolver $fencers;

    public function __construct(
        private readonly bool $rememberAliases = false,
        ?GroupResolver $groups = null,
        ?FencerResolver $fencers = null,
    ) {
        $this->groups = $groups ?? new GroupResolver();
        $this->fencers = $fencers ?? new FencerResolver();
    }

    /**
     * Everything that would stop the write, in the order the rows appear.
     *
     * @param  list<array<string, string>>  $rows
     * @return list<string>
     */
    public static function validate(array $rows): array
    {
        $problems = [];

        foreach ($rows as $index => $row) {
            $line = $index + 2;

            if (!in_array($row['aktion'], self::ACTIONS, true)) {
                $problems[] = "Zeile {$line} (\"{$row['name_datei']}\"): aktion ist \"{$row['aktion']}\", erwartet "
                    . implode(', ', self::ACTIONS);
                continue;
            }

            if ($row['aktion'] === 'skip') {
                continue;
            }

            if ($row['aktion'] === 'use' && $row['fechter_id'] === '') {
                $problems[] = "Zeile {$line} (\"{$row['name_datei']}\"): aktion \"use\", aber keine fechter_id";
            }

            if ($row['turnier'] === '') {
                $problems[] = "Zeile {$line} (\"{$row['name_datei']}\"): keine Turnier-ID";
            }

            if (!Placement::describes($row['platz'])) {
                $problems[] = "Zeile {$line} (\"{$row['name_datei']}\"): Platz \"{$row['platz']}\" lässt sich nicht "
                    . 'lesen. Erwartet wird eine Platzierung, eine Runde wie "last-16" oder "4tel Finale", '
                    . 'oder "pools" für ein Ausscheiden in der Vorrunde.';
            }
        }

        return $problems;
    }

    /**
     * @param  list<array<string, string>>  $rows
     *
     * @throws \Throwable
     */
    public function write(array $rows, bool $dryRun = false): ImportSummary
    {
        $tally = [
            'results' => 0, 'clubs' => [], 'fencers' => [], 'aliases' => [],
            'skipped' => 0, 'duplicates' => 0, 'resultIds' => [], 'aliasConflicts' => [],
        ];

        DB::beginTransaction();

        try {
            $this->run($rows, $tally);
        } catch (\Throwable $exception) {
            DB::rollBack();

            throw $exception;
        }

        $dryRun ? DB::rollBack() : DB::commit();

        return new ImportSummary(
            results: $tally['results'],
            clubs: $tally['clubs'],
            fencers: $tally['fencers'],
            aliases: $tally['aliases'],
            skipped: $tally['skipped'],
            duplicates: $tally['duplicates'],
            dryRun: $dryRun,
            resultIds: $tally['resultIds'],
            aliasConflicts: array_values($tally['aliasConflicts']),
        );
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @param  array<string, mixed>  $tally
     */
    private function run(array $rows, array &$tally): void
    {
        $tournaments = [];

        foreach ($rows as $index => $row) {
            if ($row['aktion'] === 'skip') {
                $tally['skipped']++;
                continue;
            }

            $tournamentId = $row['turnier'];

            $tournaments[$tournamentId] ??= Tournament::where('public_id', $tournamentId)->firstOrFail();
            $tournament = $tournaments[$tournamentId];

            // The sheet says how the tournament was fenced, and that decides how the placements
            // underneath are to be read - shared, worst-of-round places in a bracket, plain ranks
            // in an all-against-all. Recorded once per tournament, not per result.
            $format = trim($row['turniersystem'] ?? '');

            if ($format !== '' && $tournament->format !== $format) {
                $tournament->update(['format' => $format]);
            }

            $group = $this->group($row, $tally);
            $fencer = $this->fencer($row, $group, $tally);

            // The backstop under the planner's warning. A row can be set to "use" by hand after
            // the plan was made, and a second run over the same file would otherwise give one
            // person two placements in one tournament.
            if (Result::where('tournament_id', $tournament->id)->where('fencer_id', $fencer->id)->exists()) {
                $tally['duplicates']++;
                continue;
            }

            $result = Result::create([
                'tournament_id' => $tournament->id,
                'fencer_id'     => $fencer->id,
                // The club fenced for, written down here and not only at the person: a later
                // club change must not rewrite a placement that has already happened.
                'group_id'      => $group?->id,
                // Ours where the club is a member, which is the only thing this column decides.
                // See Group::scoringFederation().
                'federation_id' => $group?->scoringFederation()?->id,
                // What the result was, not where it finished: a rank, the round it went out in,
                // or the pool phase it never left. See App\Standings\Placement.
                'placement'     => (string) Placement::fromSource($row['platz']),
                // The raw spelling keeps the location that the club assignment loses:
                // "ESK Augsburg" rather than "Europäische Schwertkunst".
                'fencer_group_name' => $row['verein_datei'] !== '' ? $row['verein_datei'] : null,
            ]);

            $tally['resultIds'][$index] = $result->id;
            $tally['results']++;
        }
    }

    /** @param array<string, mixed> $tally */
    private function group(array $row, array &$tally): ?Group
    {
        if ($row['verein_datei'] === '') {
            return null;
        }

        if ($row['verein_id'] !== '') {
            $group = $this->groups->byPublicId($row['verein_id']);

            if (!$group) {
                throw new RuntimeException("Verein-ID unbekannt: {$row['verein_id']} (Zeile \"{$row['name_datei']}\")");
            }

            if ($this->rememberAliases) {
                $before = $group->aliases()->count();
                $conflict = $this->groups->rememberAlias($row['verein_datei'], $group);

                if ($conflict) {
                    // Said once per spelling, not once per row: a file lists a club as often as it
                    // has fencers, and twenty identical lines say nothing the first one did not.
                    $tally['aliasConflicts']["{$row['verein_datei']}|{$group->id}"]
                        = "\"{$row['verein_datei']}\" ist als Schreibweise von {$conflict->name} hinterlegt "
                        . "und bleibt dort. Die Ergebnisse dieser Datei zählen zu {$group->name}, "
                        . 'ein künftiger Import unter dieser Schreibweise aber wieder nicht.';
                } elseif ($group->aliases()->count() > $before) {
                    $tally['aliases'][] = "{$row['verein_datei']} -> {$group->name}";
                }
            }

            return $group;
        }

        // No id and not skipped means: create it. Clubs without a federation marking in the
        // file are not DDHF members, so they get no membership at all - and a club with no
        // membership scores for nobody.
        $group = Group::firstOrCreate(
            ['name' => $row['verein_datei']],
            ['is_active' => true],
        );

        if ($group->wasRecentlyCreated) {
            $tally['clubs'][] = "{$group->name} ({$group->public_id})";
            $this->groups->remember($group);
        }

        return $group;
    }

    /** @param array<string, mixed> $tally */
    private function fencer(array $row, ?Group $group, array &$tally): Fencer
    {
        if ($row['fechter_id'] !== '') {
            $fencer = $this->fencers->byPublicId($row['fechter_id']);

            if (!$fencer) {
                throw new RuntimeException("Fechter-ID unbekannt: {$row['fechter_id']} (Zeile \"{$row['name_datei']}\")");
            }

            return $fencer;
        }

        // Someone who fenced several tournaments of the same event appears once per tournament,
        // and each of those rows says "create". Without this they would become one record per
        // appearance. Only names created in this very run are reused: an existing record is a
        // different question, and the reviewer answered it by writing "create".
        //
        // The placeholder for a fencer nobody recorded is the exception. It is not a name, so two
        // rows carrying it are two unknown people, not one person appearing twice - folding them
        // together would pile unrelated placements onto a single record.
        $key = NameMatcher::normalize($row['name_datei']);
        $namesAPerson = $key !== NameMatcher::normalize(Fencer::ANONYMOUS_NAME);

        if ($namesAPerson && isset($this->createdThisRun[$key])) {
            return $this->createdThisRun[$key];
        }

        [$firstName, $lastName] = FencerResolver::splitName($row['name_datei']);

        $fencer = Fencer::create([
            // Rows already anonymised by the organisers are created disabled, following the
            // procedure in the README.
            'is_active'  => $row['aktion'] !== 'create_inactive',
            'first_name' => $firstName,
            'last_name'  => $lastName,
            'group_id'   => $group?->id,
        ]);

        $tally['fencers'][] = "{$fencer->display_name} ({$fencer->public_id})";
        $this->fencers->remember($fencer);

        if ($namesAPerson) {
            $this->createdThisRun[$key] = $fencer;
        }

        return $fencer;
    }
}
