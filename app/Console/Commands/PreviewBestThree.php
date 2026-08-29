<?php

namespace App\Console\Commands;

use App\Models\Discipline;
use App\Models\Division;
use App\Models\Event;
use App\Models\Federation;
use App\Models\Fencer;
use App\Models\Group;
use App\Models\Result;
use App\Models\ScoringMatrix;
use App\Models\Season;
use App\Models\Standing;
use App\Models\Tournament;
use App\Standings\ScoringMode;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * A Best Three standing to look at, and a way to take it away again.
 *
 * The federation has never fenced more than two tournaments in one season, so the case Best Three
 * exists for - three of five results counting, two greyed out below a rule - cannot be seen in the
 * live data. The tests cover it, but a layout wants looking at.
 *
 * Everything it makes is written down as it goes, and --remove deletes exactly that and nothing
 * else. Records are found by the ids in the note, never by their names, so this cannot reach a
 * standing somebody actually fenced even if a name collides.
 */
class PreviewBestThree extends Command
{
    protected $signature = 'standings:preview-best-three
        {--remove : Delete the preview standing and everything it brought with it}';

    protected $description = 'Legt eine Best-Three-Rangliste zum Ansehen an und räumt sie wieder weg';

    /** Where the note lives. Local disk, so it never travels with an export. */
    private const NOTE = 'standings-preview.json';

    private const FENCERS = ['Vorschau Eins', 'Vorschau Zwei', 'Vorschau Drei'];

    /** Placement per fencer and tournament, chosen so all three have something dropped. */
    private const PLACES = [
        [1, 7, 3, 14, 2],
        [4, 2, 9, 1, 11],
        [6, 5, 12, 8, 4],
    ];

    public function handle(): int
    {
        return $this->option('remove') ? $this->remove() : $this->create();
    }

    private function create(): int
    {
        if (Storage::disk('local')->exists(self::NOTE)) {
            $this->error('Es steht schon eine Vorschau. Erst aufräumen:');
            $this->line('  php artisan standings:preview-best-three --remove');

            return self::FAILURE;
        }

        // A result only scores for a member, and membership is read off the federation on the
        // result. Without our own federation on record the preview would render an empty table.
        $federation = Federation::own();

        if ($federation === null) {
            $this->error('Der eigene Dachverband fehlt in der Datenbank - ohne ihn wird nichts gewertet.');

            return self::FAILURE;
        }

        $made = [];

        DB::transaction(function () use ($federation, &$made) {
            $discipline = Discipline::create(['name' => 'Vorschau']);
            $division = Division::create(['name' => 'Best Three']);
            $standing = Standing::create([
                'discipline_id' => $discipline->id,
                'division_id'   => $division->id,
            ]);

            // One point per place away from twenty-first: first is 20, fifth is 16. Readable
            // enough that the sums can be checked on the page by eye.
            $matrix = ScoringMatrix::create([
                'name'   => 'Vorschau Best Three',
                'matrix' => json_encode([[
                    'participants' => ['min' => 1],
                    'points'       => array_map(
                        fn (int $place) => ['place' => ['min' => $place, 'points' => 21 - $place]],
                        range(1, 20),
                    ),
                ]]),
            ]);

            $season = Season::create([
                'year'              => (int) date('Y'),
                'standing_id'       => $standing->id,
                'scoring_matrix_id' => $matrix->id,
                'scoring_mode'      => ScoringMode::BestThree,
            ]);

            $club = Group::create([
                'name'      => 'Vorschau-Fechtschule',
                'is_active' => true,
            ]);

            $club->federations()->attach($federation);

            $fencers = array_map(function (string $name) use ($club) {
                [$first, $last] = explode(' ', $name, 2);

                return Fencer::create([
                    'first_name' => $first,
                    'last_name'  => $last,
                    'is_active'  => true,
                    'group_id'   => $club->id,
                ]);
            }, self::FENCERS);

            $made = [
                'season'     => $season->id,
                'standing'   => $standing->id,
                'discipline' => $discipline->id,
                'division'   => $division->id,
                'matrix'     => $matrix->id,
                'club'       => $club->id,
                'fencers'    => array_map(fn (Fencer $fencer) => $fencer->id, $fencers),
                'events'     => [],
                'tournaments' => [],
                'results'    => [],
                'public_id'  => $season->public_id,
            ];

            for ($number = 1; $number <= 5; $number++) {
                $event = Event::create([
                    'name'       => "Vorschauturnier {$number}",
                    'start_date' => sprintf('%d-%02d-15', (int) date('Y'), $number + 1),
                ]);

                $tournament = Tournament::create([
                    'season_id'         => $season->id,
                    'event_id'          => $event->id,
                    'participant_count' => 20,
                    'format'            => 'Turnierbaum',
                ]);

                $made['events'][] = $event->id;
                $made['tournaments'][] = $tournament->id;

                foreach ($fencers as $index => $fencer) {
                    $made['results'][] = Result::create([
                        'tournament_id' => $tournament->id,
                        'fencer_id'     => $fencer->id,
                        'group_id'      => $club->id,
                        'federation_id' => $federation->id,
                        'placement'     => (string) self::PLACES[$index][$number - 1],
                    ])->id;
                }
            }
        });

        Storage::disk('local')->put(self::NOTE, json_encode($made, JSON_PRETTY_PRINT));

        $this->info('Vorschau steht: eine Best-Three-Rangliste mit drei Fechtern und fünf Turnieren.');
        $this->newLine();
        $this->line('  Öffentliche Seite:  /ranglisten/' . $made['public_id']);
        $this->line('  Englisch:           /en/standings/' . $made['public_id']);
        $this->line('  Datenbrowser:       /tests/ranglisten/' . $made['public_id']);
        $this->newLine();
        $this->warn('Solange sie steht, taucht sie in der Ranglisten-Übersicht mit auf. Danach:');
        $this->line('  php artisan standings:preview-best-three --remove');

        return self::SUCCESS;
    }

    private function remove(): int
    {
        if (!Storage::disk('local')->exists(self::NOTE)) {
            $this->error('Keine Vorschau vermerkt - es gibt nichts wegzuräumen.');

            return self::FAILURE;
        }

        $made = json_decode(Storage::disk('local')->get(self::NOTE), true);

        // In the order the references point, so nothing is deleted while something still names it.
        DB::transaction(function () use ($made) {
            Result::whereIn('id', $made['results'])->delete();
            Tournament::whereIn('id', $made['tournaments'])->delete();
            Event::whereIn('id', $made['events'])->delete();
            Fencer::whereIn('id', $made['fencers'])->delete();
            Group::whereKey($made['club'])->delete();
            Season::whereKey($made['season'])->delete();
            ScoringMatrix::whereKey($made['matrix'])->delete();
            Standing::whereKey($made['standing'])->delete();
            Discipline::whereKey($made['discipline'])->delete();
            Division::whereKey($made['division'])->delete();
        });

        Storage::disk('local')->delete(self::NOTE);

        $this->info('Vorschau entfernt. ' . count($made['results']) . ' Ergebnisse, '
            . count($made['tournaments']) . ' Turniere, ' . count($made['fencers']) . ' Fechter.');

        return self::SUCCESS;
    }
}
