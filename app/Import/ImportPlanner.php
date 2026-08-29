<?php

namespace App\Import;

use App\Import\Formats\DdhfTemplate;
use App\Import\Formats\SheetFormat;
use App\Models\Result;
use App\Models\Tournament;
use App\Standings\Placement;
use RuntimeException;

/**
 * The first half of an import: match what the files say against the database, and produce a row
 * per result for somebody to look at. Nothing is written here.
 *
 * This used to be the body of the prepare command. It moved because there are now two ways in -
 * the console, which writes the rows to a csv, and the panel, which stores them as records - and
 * the matching may not differ between them.
 */
class ImportPlanner
{
    /**
     * The columns of a review row. Only "aktion" and the two id columns are meant to be edited;
     * "hinweis" is the planner talking back.
     */
    public const COLUMNS = [
        'aktion', 'datei', 'turnier', 'turniersystem', 'platz', 'name_datei', 'verein_datei',
        'verband_datei',
        'fechter_id', 'fechter_vorschlag', 'fechter_guete', 'fechter_wert',
        'verein_id', 'verein_vorschlag', 'verein_guete', 'verein_wert',
        'hinweis',
    ];

    private GroupResolver $groups;

    private FencerResolver $fencers;

    /** @var array<int, list<int>> tournament id => fencer ids that already have a result there */
    private array $alreadyThere = [];

    public function __construct(?GroupResolver $groups = null, ?FencerResolver $fencers = null)
    {
        $this->groups = $groups ?? new GroupResolver();
        $this->fencers = $fencers ?? new FencerResolver();
    }

    /**
     * Read every file of a run into one list of rows.
     *
     * All files of an event belong in one run. Someone who entered several tournaments appears in
     * several files, and the protection against creating that person twice only reaches within a
     * single run.
     *
     * @param  list<string>  $paths
     * @param  class-string<SheetFormat>  $format
     * @return list<array<string, string>>
     *
     * @throws RuntimeException
     */
    public static function read(array $paths, string $format = DdhfTemplate::class): array
    {
        $rows = [];
        $seen = [];

        foreach ($paths as $sheet => $path) {
            $read = $format::read($path);

            // Uploads carry the name they were sent under, because the stored file is named by
            // the system. A console run has only the path to go by.
            $sheet = is_string($sheet) ? $sheet : ($read[0]['sheet'] ?? pathinfo($path, PATHINFO_FILENAME));

            if (isset($seen[$sheet])) {
                // Two files of the same name from different folders. Their rows would share one
                // tournament mapping and quietly land in the same tournament.
                throw new RuntimeException(
                    "Zwei Dateien tragen den Namen \"{$sheet}\":\n  {$seen[$sheet]}\n  {$path}"
                );
            }

            $seen[$sheet] = $path;

            foreach ($read as $row) {
                $row['sheet'] = $sheet;
                $rows[] = $row;
            }
        }

        return $rows;
    }

    /**
     * Whether the files and the tournaments they are headed for say the same thing.
     *
     * The file states its field size, and the tournament carries the number the points are
     * computed from. A silent disagreement between the two would score the whole tournament
     * against the wrong bracket.
     *
     * A file named as partial is allowed to list fewer results than the field had. That happens
     * where the only surviving record is one that dropped everyone it did not rank - the 2023
     * Ochsenstich is the case this exists for. The stated field size still has to be right,
     * because the points hang on it; only the row count is let go.
     *
     * Where the tournament is still to be created, it has no count of its own to disagree with:
     * it will take the one the file states. What is left to check is that the file agrees with
     * itself, which is the half that catches a truncated sheet.
     *
     * @param  list<array<string, string>>  $rows
     * @param  array<string, Tournament|null>  $tournaments  keyed by file; null = will be created
     * @param  list<string>  $partial  files known to be recorded only in part
     */
    public static function check(array $rows, array $tournaments, array $partial = []): ConsistencyReport
    {
        $problems = [];
        $notes = [];
        $partial = array_map('trim', $partial);
        $unknown = array_diff($partial, array_keys($tournaments));

        if ($unknown !== []) {
            return new ConsistencyReport(
                ['Als unvollständig angemeldet, aber nicht dabei: ' . implode(', ', $unknown)],
            );
        }

        foreach ($tournaments as $sheet => $tournament) {
            $ofSheet = array_filter($rows, fn ($row) => $row['sheet'] === $sheet);
            $stated = array_unique(array_column($ofSheet, 'participants'));
            $counted = count($ofSheet);

            if (count($stated) > 1) {
                $problems[] = "\"{$sheet}\": die Spalte Turniergröße nennt mehrere Werte: " . implode(', ', $stated);
                continue;
            }

            // One file is one tournament, so it was fenced one way. Two answers here would mean
            // the placements of the file follow two different conventions at once.
            $systems = array_unique(array_column($ofSheet, 'system'));

            if (count($systems) > 1) {
                $problems[] = "\"{$sheet}\": die Spalte Turniersystem nennt mehrere Werte: " . implode(', ', $systems);
                continue;
            }

            $expected = $tournament?->participant_count ?? (int) reset($stated);
            $against = $tournament
                ? "das Turnier {$tournament->public_id} steht auf"
                : 'die Datei nennt';

            $checks = ['Turniergröße der Datei' => (int) reset($stated)];

            if (in_array($sheet, $partial, true)) {
                $notes[] = sprintf(
                    '"%s": %d von %d Ergebnissen, als unvollständig angemeldet.',
                    $sheet,
                    $counted,
                    $expected,
                );
            } else {
                $checks['Zeilen der Datei'] = $counted;
            }

            foreach ($checks as $what => $value) {
                if ($value !== $expected) {
                    $problems[] = sprintf(
                        '"%s": %s ist %d, %s %d Teilnehmer.',
                        $sheet,
                        $what,
                        $value,
                        $against,
                        $expected,
                    );
                }
            }
        }

        return new ConsistencyReport($problems, $notes);
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @param  array<string, Tournament|null>  $tournaments  keyed by file; null = will be created
     */
    public function plan(array $rows, array $tournaments): ImportPlan
    {
        $out = [];
        $matches = [];
        $counts = ['fechter' => [], 'verein' => []];

        foreach ($rows as $row) {
            $tournament = $tournaments[$row['sheet']] ?? null;

            // The federation column tells us whether the club is expected to exist at all.
            $isMember = $row['federation'] !== '';

            $group = $this->groups->resolve($row['club'], $isMember);
            $fencer = $this->fencers->resolve($row['name'], $group->record);

            $counts['verein'][$group->confidence->value] = ($counts['verein'][$group->confidence->value] ?? 0) + 1;
            $counts['fechter'][$fencer->confidence->value] = ($counts['fechter'][$fencer->confidence->value] ?? 0) + 1;

            // A placement the vocabulary does not cover - "Verletzung/Aufgabe" and the like -
            // always needs a human, however certain the names are. It cannot be written at all,
            // so it is the one case left blank.
            $readable = Placement::describes($row['placement']);

            // Somebody who already has a result in this tournament is not imported a second time.
            // An import brings in what is new; a wrong result is corrected elsewhere, on purpose.
            $duplicate = $fencer->found() && $this->hasResult($tournament, $fencer->record->id);

            // A candidate nobody should act on unseen. MatchConfidence::Unsure means "the nearest
            // thing there is", not "probably this" - on the fencer it would hang a result on
            // somebody else, on the club it would record a result under a club that was never
            // there. Both are silent once written, so these rows are the ones left blank.
            $doubtful = $fencer->confidence === MatchConfidence::Unsure
                || $group->confidence === MatchConfidence::Unsure;

            // Otherwise: what the matcher believes, filled in. Confirming a filled row is quick;
            // filling one in per result is not, and a field of forty is forty answers to the same
            // question. What the belief is worth stays next to it - the confidence and the score
            // are on the row, and the guesses are counted again before anything is written.
            $intent = match (true) {
                !$readable       => '',
                $duplicate       => 'skip',
                $doubtful        => '',
                $fencer->found() => 'use',
                default          => 'create',
            };

            $out[] = [
                'aktion'            => $intent,
                'datei'             => $row['sheet'],
                // Empty where the tournament is still to be created. The panel fills it in at the
                // moment of writing, when the record exists; a console run always has one.
                'turnier'           => (string) ($tournament?->public_id ?? ''),
                'turniersystem'     => $row['system'],
                // Normalised here so the reviewer reads what will be stored: an organiser's
                // "4tel Finale" arrives as last-8. Anything unreadable is handed on untouched,
                // because the reviewer has to see what the file actually said.
                'platz'             => $readable ? (string) Placement::fromSource($row['placement']) : $row['placement'],
                'name_datei'        => $row['name'],
                'verein_datei'      => $row['club'],
                'verband_datei'     => $row['federation'],
                'fechter_id'        => $fencer->publicId(),
                'fechter_vorschlag' => $fencer->name(),
                'fechter_guete'     => $fencer->confidence->label(),
                'fechter_wert'      => $fencer->found() ? sprintf('%.2f', $fencer->score) : '',
                'verein_id'         => $group->publicId(),
                'verein_vorschlag'  => $group->name(),
                'verein_guete'      => $group->confidence->label(),
                'verein_wert'       => $group->found() ? sprintf('%.2f', $group->score) : '',
                'hinweis'           => match (true) {
                    $duplicate => 'Ergebnis in diesem Turnier existiert bereits',
                    $doubtful  => 'Nur ein ungefährer Treffer — bitte ansehen',
                    default    => '',
                },
            ];

            $matches[] = [
                'sheet'  => $row['sheet'],
                'row'    => (int) ($row['row'] ?? 0),
                'fencer' => $fencer,
                'group'  => $group,
            ];
        }

        return new ImportPlan($out, $counts, $matches);
    }

    /** Loaded once per tournament rather than once per row. */
    private function hasResult(?Tournament $tournament, int $fencerId): bool
    {
        // A tournament that does not exist yet holds no results to collide with.
        if (!$tournament?->exists) {
            return false;
        }

        $this->alreadyThere[$tournament->id] ??= Result::where('tournament_id', $tournament->id)
            ->pluck('fencer_id')
            ->all();

        return in_array($fencerId, $this->alreadyThere[$tournament->id], true);
    }
}
