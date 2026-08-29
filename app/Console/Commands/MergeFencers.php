<?php

namespace App\Console\Commands;

use App\Models\Fencer;
use App\Models\Result;
use App\Models\ResultImportRow;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One person, entered twice. This folds the copies into the record that is right.
 *
 * It happens because a name arrives spelled differently from one year to the next and the matcher
 * cannot be sure enough to join them - which is the correct answer for a matcher to give. A person
 * who can be sure says so here.
 *
 * Records are named by their public id and never by their name. Two records worth merging are two
 * records whose names differ, so a name is exactly the wrong handle; and the ids are what the data
 * browser puts in front of whoever noticed.
 *
 * The merged record is deleted, which means its public id stops resolving. Nothing outside has
 * been handed one yet. The dump taken before the run is the way back.
 */
class MergeFencers extends Command
{
    protected $signature = 'fencers:merge
        {keep : Public ID of the record that stays}
        {merge* : Public IDs of the records folded into it}
        {--dry-run : Say what would happen and change nothing}';

    protected $description = 'Legt doppelt erfasste Fechter zu einem Datensatz zusammen';

    /** Filled in from the merged records where the survivor has nothing and the sources agree. */
    private const FILLABLE_GAPS = [
        'title', 'birth_name', 'nationality', 'date_of_birth', 'gender', 'group_id',
    ];

    public function handle(): int
    {
        $keep = $this->fencer($this->argument('keep'));
        $merge = [];

        foreach ($this->argument('merge') as $publicId) {
            $merge[] = $this->fencer($publicId);
        }

        if ($keep === null || in_array(null, $merge, true)) {
            return self::FAILURE;
        }

        if ($problems = $this->problems($keep, $merge)) {
            foreach ($problems as $problem) {
                $this->error($problem);
            }

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        DB::beginTransaction();

        try {
            $moved = $this->fold($keep, $merge);
        } catch (\Throwable $exception) {
            DB::rollBack();

            throw $exception;
        }

        $dryRun ? DB::rollBack() : DB::commit();

        $this->report($keep, $merge, $moved, $dryRun);

        return self::SUCCESS;
    }

    private function fencer(string $publicId): ?Fencer
    {
        $fencer = Fencer::where('public_id', $publicId)->first();

        if (!$fencer) {
            $this->error("Fechter nicht gefunden: {$publicId}");
        }

        return $fencer;
    }

    /**
     * Everything that stops the merge.
     *
     * @param  list<Fencer>  $merge
     * @return list<string>
     */
    private function problems(Fencer $keep, array $merge): array
    {
        $problems = [];

        foreach ($merge as $fencer) {
            if ($fencer->id === $keep->id) {
                $problems[] = "{$fencer->public_id} ist der Datensatz, der bleiben soll.";
            }
        }

        foreach ([$keep, ...$merge] as $fencer) {
            // Anonymising deleted what identified somebody. Merging is a statement that two
            // records are the same person, and there is no longer anything here to check that
            // against - so it is not a call this command may make.
            if ($fencer->isAnonymized()) {
                $problems[] = "{$fencer->public_id} ist anonymisiert und wird nicht zusammengelegt.";
            }
        }

        // Two placements for one person in one tournament is the thing that must not come out of
        // this. The importer refuses it at the write; so does this.
        $ids = array_map(fn (Fencer $fencer) => $fencer->id, [$keep, ...$merge]);

        $clashes = Result::query()
            ->whereIn('fencer_id', $ids)
            ->select('tournament_id', DB::raw('count(*) as n'))
            ->groupBy('tournament_id')
            ->having('n', '>', 1)
            ->pluck('n', 'tournament_id');

        foreach ($clashes as $tournamentId => $count) {
            $tournament = \App\Models\Tournament::find($tournamentId);
            $problems[] = "Turnier {$tournament?->public_id}: die Datensätze haben dort zusammen "
                . "{$count} Ergebnisse. Eine Person kann in einem Turnier nur einmal platziert sein "
                . '— bitte erst klären, welches Ergebnis stimmt.';
        }

        return $problems;
    }

    /**
     * @param  list<Fencer>  $merge
     * @return array<string, mixed>
     */
    private function fold(Fencer $keep, array $merge): array
    {
        $ids = array_map(fn (Fencer $fencer) => $fencer->id, $merge);

        $moved = [
            'results' => Result::whereIn('fencer_id', $ids)->update(['fencer_id' => $keep->id]),
            'rows'    => ResultImportRow::whereIn('fencer_id', $ids)->update(['fencer_id' => $keep->id]),
            'seasons' => 0,
            'filled'  => [],
            'unclear' => [],
        ];

        // The category a fencer is ranked in, per season. Attached rather than updated, because
        // both records may already point at the same season and the pair is unique.
        foreach ($merge as $fencer) {
            foreach ($fencer->seasons()->pluck('seasons.id') as $seasonId) {
                if (!$keep->seasons()->whereKey($seasonId)->exists()) {
                    $keep->seasons()->attach($seasonId);
                    $moved['seasons']++;
                }
            }

            $fencer->seasons()->detach();
        }

        $this->fillGaps($keep, $merge, $moved);

        foreach ($merge as $fencer) {
            $fencer->delete();
        }

        return $moved;
    }

    /**
     * What the survivor was missing and one of the others knew.
     *
     * Only where the survivor has nothing at all, and only where the records that do have
     * something all say the same thing. A disagreement is reported and left alone: this command
     * knows that two records are one person because somebody said so, which is no reason to
     * believe it can pick between two birth dates.
     *
     * @param  list<Fencer>  $merge
     * @param  array<string, mixed>  $moved
     */
    private function fillGaps(Fencer $keep, array $merge, array &$moved): void
    {
        foreach (self::FILLABLE_GAPS as $field) {
            if (filled($keep->getAttribute($field))) {
                continue;
            }

            $candidates = [];

            foreach ($merge as $fencer) {
                $value = $fencer->getAttribute($field);

                if (filled($value)) {
                    $candidates[(string) $value] = $value;
                }
            }

            if (count($candidates) === 1) {
                $keep->setAttribute($field, reset($candidates));
                $moved['filled'][$field] = (string) array_key_first($candidates);
            } elseif (count($candidates) > 1) {
                $moved['unclear'][$field] = array_keys($candidates);
            }
        }

        if ($keep->isDirty()) {
            $keep->save();
        }
    }

    /**
     * @param  list<Fencer>  $merge
     * @param  array<string, mixed>  $moved
     */
    private function report(Fencer $keep, array $merge, array $moved, bool $dryRun): void
    {
        $this->newLine();

        if ($dryRun) {
            $this->warn('Probelauf — nichts wurde geschrieben.');
            $this->newLine();
        }

        $folded = implode(', ', array_map(fn (Fencer $fencer) => $fencer->public_id, $merge));

        $this->info("{$folded} → {$keep->public_id}");
        $this->line("  {$moved['results']} Ergebnisse übernommen");

        if ($moved['rows'] > 0) {
            $this->line("  {$moved['rows']} Importzeilen umgehängt");
        }

        if ($moved['seasons'] > 0) {
            $this->line("  {$moved['seasons']} Ranglistenzuordnungen übernommen");
        }

        foreach ($moved['filled'] as $field => $value) {
            $this->line("  {$field} war leer und ist jetzt: {$value}");
        }

        foreach ($moved['unclear'] as $field => $values) {
            $this->warn("  {$field}: die Datensätze widersprechen sich (" . implode(' / ', $values)
                . ') — Feld bleibt leer, bitte von Hand setzen.');
        }

        $this->newLine();
        $this->warn(count($merge) . ' Datensätze gelöscht. Ihre IDs lösen ab jetzt nicht mehr auf.');
    }
}
