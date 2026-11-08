<?php

namespace App\Import;

use App\Models\Fencer;
use App\Models\Group;
use App\Models\Result;
use App\Models\Tournament;
use App\Models\TournamentFormat;
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
    /**
     * What the "aktion" column of a reviewed row may say.
     *
     * Whether the person may be named is not among them. It used to be - "create_inactive", for a
     * row the organisers had already anonymised - and that folded two questions into one answer:
     * the record it made was disabled, but kept the placeholder as its name and was never marked
     * as anonymised. It is the "anonymisieren" column now, next to this one.
     */
    public const ACTIONS = ['use', 'create', 'skip'];

    /** The actions that make a new record, whatever fechter_id says. */
    public const CREATING = ['create'];

    /**
     * The same three, said in a way somebody choosing between them can act on.
     *
     * Every one of them names the fencer, because that is what two of them decide and what the
     * third skips a whole row over. The club is not among them: it is used when one is picked and
     * created from the file's spelling when none is, and saying "neu anlegen" in two columns that
     * mean different things is exactly how this got read as being about the club.
     */
    public const ACTION_LABELS = [
        'use'    => 'Diese Person verwenden',
        'create' => 'Person neu anlegen',
        'skip'   => 'Zeile überspringen',
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
            $anonymise = self::anonymises($row);

            if ($row['aktion'] === 'create_inactive') {
                $problems[] = "Zeile {$line} (\"{$row['name_datei']}\"): aktion \"create_inactive\" gibt es nicht mehr. "
                    . 'Eine Zeile, die schon von den Veranstaltenden anonymisiert wurde, ist "create" mit anonymisieren = 1.';
                continue;
            }

            if (!in_array($row['aktion'], self::ACTIONS, true)) {
                $problems[] = "Zeile {$line} (\"{$row['name_datei']}\"): aktion ist \"{$row['aktion']}\", erwartet "
                    . implode(', ', self::ACTIONS);
                continue;
            }

            // Anonymising goes with a new record only. Somebody already on record is anonymised in
            // the panel, on purpose and with a reason, not in passing by an import.
            if ($anonymise && $row['aktion'] !== 'create') {
                $problems[] = "Zeile {$line} (\"{$row['name_datei']}\"): anonymisieren geht nur zusammen mit \"create\".";
                continue;
            }

            if ($row['aktion'] === 'skip') {
                continue;
            }

            // The review file of a console run is edited by hand, and the system is a choice from
            // a list now rather than whatever was typed.
            $system = trim($row['turniersystem'] ?? '');

            if ($system !== '' && !TournamentFormat::where('name', $system)->exists()) {
                $problems[] = "Zeile {$line} (\"{$row['name_datei']}\"): Turniersystem \"{$system}\" gibt es nicht. "
                    . 'Zur Auswahl: ' . implode(', ', TournamentFormat::options()) . '.';
            }

            // The mistake the column exists to end: the placeholder stored as somebody's name,
            // which reads as anonymous and is marked as such nowhere.
            if (!$anonymise && NameMatcher::normalize($row['name_datei']) === NameMatcher::normalize(Fencer::ANONYMOUS_NAME)) {
                $problems[] = "Zeile {$line} (\"{$row['name_datei']}\"): Das ist der Platzhalter für anonyme "
                    . 'Startende, kein Name. Bitte anonymisieren ankreuzen.';
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
     * Whether a row asks for its new record to be anonymised from the start.
     *
     * @param  array<string, string>  $row
     */
    public static function anonymises(array $row): bool
    {
        return ($row['anonymisieren'] ?? '') === '1';
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

            // No club for an anonymous starter, neither looked up nor created from the file: once
            // everything else is gone it is the last trait left, and in a small club it is enough
            // to work back to a person. The same reason AnonymizeFencerAction clears it.
            $group = self::anonymises($row) ? null : $this->group($row, $tally);
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
                'fencer_group_name' => !self::anonymises($row) && $row['verein_datei'] !== '' ? $row['verein_datei'] : null,
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
        //
        // Unless it is already there under this name in another spelling - most often because an
        // earlier row of this run created it. The lookup folds the name: the stored one has been
        // normalised, so asking for the file's spelling as written misses it, and Buuredanz 2026
        // got one club twice that way.
        if ($existing = $this->groups->named($row['verein_datei'])) {
            return $existing;
        }

        $group = Group::create(['name' => $row['verein_datei'], 'is_active' => true]);

        $tally['clubs'][] = "{$group->name} ({$group->public_id})";
        $this->groups->remember($group);

        return $group;
    }

    /** @param array<string, mixed> $tally */
    private function fencer(array $row, ?Group $group, array &$tally): Fencer
    {
        // The action decides, not whether an id happens to be filled in. A row switched to "neu
        // anlegen" can still carry the suggestion it started with - from a file whose id nobody
        // cleared, or from a screen that kept it - and reusing that person would do the opposite
        // of what the reviewer asked for.
        if ($row['aktion'] === 'use') {
            $fencer = $this->fencers->byPublicId($row['fechter_id']);

            if (!$fencer) {
                throw new RuntimeException("Fechter:in-ID unbekannt: {$row['fechter_id']} (Zeile \"{$row['name_datei']}\")");
            }

            return $fencer;
        }

        // Someone who fenced several tournaments of the same event appears once per tournament,
        // and each of those rows says "create". Without this they would become one record per
        // appearance. Only names created in this very run are reused: an existing record is a
        // different question, and the reviewer answered it by writing "create".
        //
        // An anonymous starter is the exception. There is no name, so two such rows are two
        // unknown people, not one person appearing twice - folding them together would pile
        // unrelated placements onto a single record.
        $key = NameMatcher::normalize($row['name_datei']);
        $namesAPerson = !self::anonymises($row);

        if ($namesAPerson && isset($this->createdThisRun[$key])) {
            return $this->createdThisRun[$key];
        }

        if ($namesAPerson) {
            [$firstName, $lastName] = FencerResolver::splitName($row['name_datei']);

            $fencer = Fencer::create([
                'is_active'  => true,
                'first_name' => $firstName,
                'last_name'  => $lastName,
                'group_id'   => $group?->id,
            ]);
        } else {
            // Anonymised from the start, in the shape the panel's action leaves behind: no name
            // stored at all, so the placeholder comes from Fencer::display_name and nothing can
            // mistake it for somebody.
            $fencer = Fencer::create([
                'is_active'            => false,
                'first_name'           => null,
                'last_name'            => null,
                'anonymized_at'        => now(),
                'anonymization_reason' => Fencer::ANONYMISED_BY_ORGANISERS,
            ]);
        }

        $tally['fencers'][] = "{$fencer->display_name} ({$fencer->public_id})";
        $this->fencers->remember($fencer);

        if ($namesAPerson) {
            $this->createdThisRun[$key] = $fencer;
        }

        return $fencer;
    }
}
