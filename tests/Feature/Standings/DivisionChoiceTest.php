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
use App\Standings\StandingCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * One weapon, one year, two categories - and a fencer belongs in one of them.
 */
class DivisionChoiceTest extends TestCase
{
    use RefreshDatabase;

    private Season $offen;

    private Season $damen;

    private Federation $ddhf;

    private Group $club;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ddhf = Federation::create([
            'name'         => 'Deutscher Dachverband für historisches Fechten e. V.',
            'abbreviation' => Federation::OWN,
            'is_active'    => true,
        ]);

        $this->club = Group::create([
            'name'          => 'Schildwache Potsdam',
            'is_active'     => true,
        ]);

        $this->club->federations()->attach($this->ddhf);

        $matrix = ScoringMatrix::create([
            'name'   => 'Punkteschlüssel Test',
            'matrix' => json_encode([[
                'participants' => ['min' => 1],
                'points'       => [
                    ['place' => ['min' => 1, 'points' => 10]],
                    ['place' => ['min' => 2, 'points' => 6]],
                ],
            ]]),
        ]);

        $discipline = Discipline::create(['name' => 'Langes Schwert']);

        // Same weapon, same year, two categories: these two are each other's alternative.
        $this->offen = $this->season($discipline, 'offen', $matrix);
        $this->damen = $this->season($discipline, 'Damen+', $matrix);
    }

    private function season(Discipline $discipline, string $division, ScoringMatrix $matrix): Season
    {
        return Season::create([
            'year'              => 2025,
            'standing_id'       => Standing::create([
                'discipline_id' => $discipline->id,
                'division_id'   => Division::create(['name' => $division])->id,
            ])->id,
            'scoring_matrix_id' => $matrix->id,
            'scoring_mode'      => ScoringMode::Standard,
        ]);
    }

    private function fencer(string $first): Fencer
    {
        return Fencer::create([
            'first_name' => $first,
            'last_name'  => 'Fechterin',
            'is_active'  => true,
            'group_id'   => $this->club->id,
        ]);
    }

    private function placed(Season $season, Fencer $fencer, int $place): void
    {
        $tournament = Tournament::create([
            'season_id'         => $season->id,
            'event_id'          => Event::create(['name' => 'Dresdner Fechtschul', 'start_date' => '2025-10-26'])->id,
            'participant_count' => 20,
        ]);

        Result::create([
            'tournament_id' => $tournament->id,
            'fencer_id'     => $fencer->id,
            'group_id'      => $this->club->id,
            'federation_id' => $this->ddhf->id,
            'placement'     => (string) $place,
        ]);
    }

    /** @return array<string, int> */
    private function standing(Season $season): array
    {
        $loaded = $season->fresh()->load('tournaments.results.fencer.group', 'tournaments.ruleset');

        $calculator = new StandingCalculator(
            ScoringMatrixEvaluator::fromJson($season->scoring_matrix->matrix),
            $season->scoring_mode,
        );

        $points = [];

        foreach ($calculator->calculate($loaded) as $entry) {
            $points[$entry['fencer']->display_name] = $entry['points'];
        }

        return $points;
    }

    public function test_without_the_requirement_she_stays_in_both(): void
    {
        $fencer = $this->fencer('Test');
        $this->placed($this->offen, $fencer, 2);
        $this->placed($this->damen, $fencer, 1);

        $this->assertArrayHasKey('Test Fechterin', $this->standing($this->offen));
        $this->assertArrayHasKey('Test Fechterin', $this->standing($this->damen));
    }

    public function test_the_declaration_decides_which_ranking_she_appears_in(): void
    {
        $fencer = $this->fencer('Test');
        $this->placed($this->offen, $fencer, 2);
        $this->placed($this->damen, $fencer, 1);

        $this->offen->update(['division_choice_required' => true]);
        $this->damen->update(['division_choice_required' => true]);
        $this->damen->fencers()->attach($fencer);

        $this->assertArrayNotHasKey('Test Fechterin', $this->standing($this->offen));
        $this->assertSame(10, $this->standing($this->damen)['Test Fechterin']);
    }

    public function test_without_a_declaration_she_appears_in_neither(): void
    {
        // The decision taken here: somebody missing from both lists is a question that gets asked,
        // somebody standing in both is a wrong ranking that nobody notices.
        $fencer = $this->fencer('Test');
        $this->placed($this->offen, $fencer, 2);
        $this->placed($this->damen, $fencer, 1);

        $this->offen->update(['division_choice_required' => true]);
        $this->damen->update(['division_choice_required' => true]);

        $this->assertSame([], $this->standing($this->offen));
        $this->assertSame([], $this->standing($this->damen));
    }

    public function test_whoever_fenced_only_one_category_needs_no_declaration(): void
    {
        // The ordinary case, and the reason the requirement costs almost nobody anything: with no
        // result in the other category there was never a choice to make.
        $fencer = $this->fencer('Nina');
        $this->placed($this->offen, $fencer, 1);

        $this->offen->update(['division_choice_required' => true]);
        $this->damen->update(['division_choice_required' => true]);

        $this->assertSame(10, $this->standing($this->offen)['Nina Fechterin']);
    }

    public function test_a_declaration_in_one_year_says_nothing_about_the_next(): void
    {
        $fencer = $this->fencer('Test');
        $this->placed($this->offen, $fencer, 2);
        $this->placed($this->damen, $fencer, 1);

        $this->offen->update(['division_choice_required' => true]);
        $this->damen->update(['division_choice_required' => true]);
        $this->damen->fencers()->attach($fencer);

        $this->assertTrue($this->damen->fencers()->where('fencers.id', $fencer->id)->exists());
        $this->assertFalse($this->offen->fencers()->where('fencers.id', $fencer->id)->exists());
    }

    public function test_deleting_a_fencer_takes_the_declaration_with_it(): void
    {
        $fencer = $this->fencer('Test');
        $this->damen->fencers()->attach($fencer);

        $fencer->delete();

        $this->assertSame(0, $this->damen->fencers()->count());
    }

    public function test_the_alternatives_are_the_same_weapon_and_year(): void
    {
        $andere = $this->season(Discipline::create(['name' => 'Rapier']), 'Damen+', $this->offen->scoring_matrix);

        $siblings = $this->offen->siblings()->pluck('id');

        $this->assertTrue($siblings->contains($this->damen->id));
        $this->assertFalse($siblings->contains($andere->id));
        $this->assertFalse($siblings->contains($this->offen->id));
    }
}
