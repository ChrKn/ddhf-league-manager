<?php

namespace Tests\Feature\Standings;

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
use App\Standings\ScoringMatrixEvaluator;
use App\Standings\ScoringMode;
use App\Standings\SeasonRanking;
use App\Standings\SeasonScoring;
use App\Standings\TournamentResults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The season's own threshold, kept apart from the matrix.
 *
 * The official table is one table, and open longsword is the standing that leaves out its three
 * smallest columns. Here that is a number on the season - and the case it was built for is a
 * field of 22 where the season scores from 25.
 */
class SeasonThresholdTest extends TestCase
{
    use RefreshDatabase;

    private Group $club;

    private ScoringMatrix $standard;

    private int $fencers = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $ddhf = Federation::create([
            'name'         => 'Deutscher Dachverband für historisches Fechten e. V.',
            'abbreviation' => Federation::OWN,
            'is_active'    => true,
        ]);

        $this->club = Group::create(['name' => 'Ochs', 'is_active' => true]);
        $this->club->federations()->attach($ddhf);

        $this->standard = $this->matrix('2026-standard');
    }

    private function matrix(string $file): ScoringMatrix
    {
        return ScoringMatrix::create([
            'name'   => $file,
            'matrix' => file_get_contents(base_path("database/scoring-matrices/{$file}.json")),
        ]);
    }

    private function season(?int $threshold, ?ScoringMatrix $matrix = null): Season
    {
        return Season::create([
            'year'              => 2026,
            'is_public'         => true,
            'standing_id'       => Standing::create([
                'discipline_id' => Discipline::firstOrCreate(['name' => 'Langes Schwert'])->id,
                'division_id'   => Division::firstOrCreate(['name' => 'offen'])->id,
            ])->id,
            'scoring_matrix_id' => ($matrix ?? $this->standard)->id,
            'scoring_mode'      => ScoringMode::Standard,
            'min_participants'  => $threshold,
        ]);
    }

    /** A tournament of this size, with the first three places filled. */
    private function tournament(Season $season, int $participants, array $attributes = []): Tournament
    {
        $tournament = Tournament::create([
            'season_id'         => $season->id,
            'event_id'          => Event::create([
                'name'       => 'Turnier ' . $participants,
                'start_date' => '2026-05-0' . (Tournament::count() % 9 + 1),
            ])->id,
            'participant_count' => $participants,
            ...$attributes,
        ]);

        foreach ([1, 2, 3] as $place) {
            Result::create([
                'tournament_id'     => $tournament->id,
                'fencer_id'         => Fencer::create([
                    'first_name' => 'Test',
                    'last_name'  => 'Nummer ' . ++$this->fencers,
                    'is_active'  => true,
                ])->id,
                'group_id'          => $this->club->id,
                'federation_id'     => $this->club->scoringFederation()?->id,
                'placement'         => $place,
                'fencer_group_name' => $this->club->name,
            ]);
        }

        return $tournament;
    }

    private function loaded(Season $season): Season
    {
        return Season::with(SeasonRanking::relations())->find($season->id);
    }

    /** @return list<int> The points of the table, top down. */
    private function points(Season $season): array
    {
        return array_column(SeasonRanking::for($this->loaded($season))['rows'], 'points');
    }

    private function evaluator(ScoringMatrix $matrix): ScoringMatrixEvaluator
    {
        return ScoringMatrixEvaluator::fromJson($matrix->matrix);
    }

    public function test_a_field_below_the_seasons_threshold_is_not_scored(): void
    {
        // Der Anlassfall ohne Beschluss: 22 Angetretene, die Saison wertet ab 25. Die Matrix hat
        // für 22 eine Spalte - die Saison lässt sie trotzdem nicht zu.
        $season = $this->season(25);
        $tournament = $this->tournament($season, 22);

        $this->assertFalse(SeasonScoring::of($season)->scores($tournament));
        // Listed, as anybody from an unscored field always was - with nothing.
        $this->assertSame([0, 0, 0], $this->points($season));
        $this->assertSame([], SeasonRanking::steps($this->loaded($season)));
        $this->assertSame(
            [0, 0, 0],
            array_column(TournamentResults::for($tournament->load(TournamentResults::RELATIONS)), 'points'),
        );
    }

    public function test_a_field_at_the_threshold_is_scored_from_the_seasons_matrix(): void
    {
        $season = $this->season(25);
        $this->tournament($season, 25);

        $band = $this->evaluator($this->standard);

        $this->assertSame([
            $band->pointsFor(25, '1'),
            $band->pointsFor(25, '2'),
            $band->pointsFor(25, '3'),
        ], $this->points($season));
        $this->assertCount(1, SeasonRanking::steps($this->loaded($season)));
    }

    /** @return array<string, array{0: string, 1: int}> */
    public static function fieldSizes(): array
    {
        $cases = [];

        foreach (['2026-standard', '2026-langschwert-offen'] as $file) {
            foreach ([1, 3, 4, 5, 8, 9, 16, 17, 22, 24, 25, 32, 40] as $size) {
                $cases["{$file}, {$size}"] = [$file, $size];
            }
        }

        return $cases;
    }

    #[DataProvider('fieldSizes')]
    public function test_without_a_threshold_of_its_own_the_matrix_decides_as_before(string $file, int $size): void
    {
        // Keine Saisonschwelle, keine Änderung: dieselben Punkte wie der Punkteschlüssel allein.
        $matrix = $this->matrix($file);
        $season = $this->season(null, $matrix);
        $tournament = $this->tournament($season, $size);

        $alone = $this->evaluator($matrix);
        $expected = array_values(array_filter([
            $alone->pointsFor($size, '1'),
            $alone->pointsFor($size, '2'),
            $alone->pointsFor($size, '3'),
        ], fn (int $points) => $points > 0));

        rsort($expected);
        $points = $this->points($season);
        rsort($points);

        $this->assertSame($expected, array_values(array_filter($points)));
        $this->assertSame($alone->hasBandFor($size), SeasonScoring::of($season)->scores($tournament));
    }

    public function test_a_column_of_zeros_is_no_column(): void
    {
        // Die 25er-Matrix hat unten ein Band, das nichts vergibt. Ein Feld von 22 fand dort bisher
        // "eine Spalte" und wurde als Schritt der Rangliste geführt, obwohl niemand Punkte bekam.
        $langschwert = $this->evaluator($this->matrix('2026-langschwert-offen'));

        $this->assertFalse($langschwert->hasBandFor(22));
        $this->assertTrue($langschwert->hasBandFor(25));
        $this->assertFalse($this->evaluator($this->standard)->hasBandFor(4));
        $this->assertTrue($this->evaluator($this->standard)->hasBandFor(5));
    }

    public function test_the_standing_says_where_the_seasons_threshold_lies(): void
    {
        $season = $this->season(25);

        $this->get("/ranglisten/{$season->public_id}")
            ->assertOk()
            ->assertSee('<dt>Gewertet ab</dt>', false)
            ->assertSee('25 Angetretenen (Saisonregel)');

        $this->get("/en/standings/{$season->public_id}")
            ->assertOk()
            ->assertSee('25 participants (season rule)');

        // Und neben der Rangliste, die die Regel hat, auf der Seite des Punkteschlüssels.
        $this->get("/wertung/{$this->standard->public_id}")
            ->assertOk()
            ->assertSee('gewertet ab 25 Angetretenen (Saisonregel)')
            ->assertSee('Unter 5 Teilnehmenden gibt es keine Punkte.');
    }

    public function test_a_season_without_a_threshold_of_its_own_says_nothing_about_one(): void
    {
        $season = $this->season(null);

        $this->get("/ranglisten/{$season->public_id}")
            ->assertOk()
            ->assertDontSee('Gewertet ab');
    }
}
