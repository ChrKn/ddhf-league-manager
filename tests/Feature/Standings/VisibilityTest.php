<?php

namespace Tests\Feature\Standings;

use App\Models\ApiKey;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A club or federation may ask not to be named. That is a third thing, next to the two that
 * already existed:
 *
 *   is_active   whether it still exists - a statement of fact about the organisation
 *   anonymised  for people, and it deletes
 *   is_public   whether it may be named where the public can read it
 *
 * The point worth guarding is what does *not* change. Nothing is deleted, every result keeps
 * pointing at the record, and the standings are computed from exactly the same rows - so the
 * fencers keep their places and their points, and only the name goes.
 */
class VisibilityTest extends TestCase
{
    use RefreshDatabase;

    private Federation $ddhf;

    private Group $shy;

    private Group $other;

    private Season $season;

    private Tournament $tournament;

    private string $key;

    protected function setUp(): void
    {
        parent::setUp();

        // The data browser is part of the admin panel now.
        $this->actingAsMaintainer();

        $this->ddhf = Federation::create([
            'name'         => 'Deutscher Dachverband für historisches Fechten e. V.',
            'abbreviation' => Federation::OWN,
            'is_active'    => true,
        ]);

        $this->shy = Group::create([
            'name'          => 'Fechtschule Musterstadt',
            'is_active'     => true,
        ]);

        $this->shy->federations()->attach($this->ddhf);

        $this->other = Group::create([
            'name'          => 'Ochs Historische Kampfkünste',
            'is_active'     => true,
        ]);

        $this->other->federations()->attach($this->ddhf);

        $this->season = Season::create([
            'year'              => (int) now()->year,
            'standing_id'       => Standing::create([
                'discipline_id' => Discipline::create(['name' => 'Langes Schwert'])->id,
                'division_id'   => Division::create(['name' => 'offen'])->id,
            ])->id,
            'scoring_matrix_id' => ScoringMatrix::create([
                'name'   => 'Punkteschlüssel Test',
                'matrix' => json_encode([[
                    'participants' => ['min' => 0],
                    'points'       => [
                        ['place' => ['min' => 1, 'points' => 10]],
                        ['place' => ['min' => 2, 'points' => 6]],
                    ],
                ]]),
            ])->id,
            'scoring_mode' => ScoringMode::Standard,
        ]);

        $this->tournament = Tournament::create([
            'season_id'         => $this->season->id,
            'event_id'          => Event::create([
                'name'       => 'Musterturnier',
                'start_date' => now()->startOfYear()->addMonths(2)->toDateString(),
            ])->id,
            'participant_count' => 2,
        ]);

        $this->fence($this->shy, 'Erika', 'Mustermann', 1);
        $this->fence($this->other, 'Otto', 'Probstett', 2);

        $this->key = ApiKey::create(['name' => 'Test', 'key' => str_repeat('V', 64)])->key;
    }

    private function fence(Group $club, string $first, string $last, int $placement): void
    {
        Result::create([
            'tournament_id' => $this->tournament->id,
            'fencer_id'     => Fencer::create([
                'first_name' => $first,
                'last_name'  => $last,
                'is_active'  => true,
                'group_id'   => $club->id,
            ])->id,
            'group_id'          => $club->id,
            'federation_id'     => $club->scoringFederation()?->id,
            'placement'         => (string) $placement,
            'fencer_group_name' => $club->name,
        ]);
    }

    private function api(string $path): \Illuminate\Testing\TestResponse
    {
        return $this->withToken($this->key)->getJson($path);
    }

    public function test_hiding_a_club_changes_no_points_and_no_places(): void
    {
        $before = $this->api("/api/seasons/{$this->season->public_id}/standing")->json('standing');

        $this->shy->update(['is_public' => false]);

        $after = $this->api("/api/seasons/{$this->season->public_id}/standing")->json('standing');

        // Same people, same order, same points. This is the whole promise.
        $this->assertCount(2, $after);
        $this->assertSame(
            array_column(array_column($before, 'fencer'), 'name'),
            array_column(array_column($after, 'fencer'), 'name'),
        );
        $this->assertSame(array_column($before, 'points'), array_column($after, 'points'));

        // Only the club is gone, and only for the club that asked.
        $this->assertSame('Fechtschule Musterstadt', $before[0]['fencer']['group']);
        $this->assertNull($after[0]['fencer']['group']);
        $this->assertSame('Ochs Historische Kampfkünste', $after[1]['fencer']['group']);
    }

    public function test_the_club_is_gone_from_the_public_site_including_the_fencers_row(): void
    {
        $this->shy->update(['is_public' => false]);

        foreach ([
            '/',
            '/ranglisten/' . $this->season->public_id,
            '/ddhf-turniere/' . $this->tournament->public_id,
            '/en/standings/' . $this->season->public_id,
        ] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertDontSee('Fechtschule Musterstadt')
                // The fencer stays: the request was the club's, and the placement is hers.
                ->assertSee('Erika Mustermann')
                ->assertSee('Ochs Historische Kampfkünste');
        }
    }

    public function test_the_api_stops_resolving_a_hidden_club(): void
    {
        $this->shy->update(['is_public' => false]);

        $this->api('/api/groups')
            ->assertOk()
            ->assertJsonMissing(['name' => 'Fechtschule Musterstadt'])
            ->assertJsonFragment(['name' => 'Ochs Historische Kampfkünste']);

        $this->api("/api/groups/{$this->shy->public_id}")->assertNotFound();
        $this->api("/api/groups/{$this->other->public_id}")->assertOk();

        // And hands out no id for it either, or the 404 above would be reachable from a link
        // the API itself gave out.
        $fencer = Fencer::where('last_name', 'Mustermann')->first();
        $this->api("/api/fencers/{$fencer->public_id}")
            ->assertOk()
            ->assertJsonPath('data.group', null)
            ->assertJsonPath('data.group_id', null);
    }

    public function test_a_hidden_federation_is_not_named_but_still_decides_who_scores(): void
    {
        $this->ddhf->update(['is_public' => false]);

        $this->api('/api/federations')
            ->assertOk()
            ->assertJsonMissing(['name' => $this->ddhf->name]);

        $this->api("/api/federations/{$this->ddhf->public_id}")->assertNotFound();

        // A club still names no federation it may not name.
        $this->api("/api/groups/{$this->other->public_id}")
            ->assertOk()
            ->assertJsonPath('data.federation', null);

        // But the standing is untouched: membership is read off the result, and Federation::own()
        // does not care whether anybody wants to be named.
        $standing = $this->api("/api/seasons/{$this->season->public_id}/standing")->json('standing');
        $this->assertCount(2, $standing);
        $this->assertSame(10, $standing[0]['points']);
    }

    public function test_hiding_is_not_deactivating(): void
    {
        $this->shy->update(['is_public' => false]);
        $this->shy->refresh();

        // The two say different things and neither implies the other. A club that asked not to be
        // named is still fencing.
        $this->assertTrue((bool) $this->shy->is_active);
        $this->assertFalse($this->shy->isPublic());

        // And the record itself keeps everything - this is not anonymisation.
        $this->assertSame('Fechtschule Musterstadt', $this->shy->name);
        $this->assertTrue($this->shy->federations->contains($this->ddhf));
        $this->assertSame(1, Result::where('group_id', $this->shy->id)->count());
    }

    public function test_the_internal_browser_still_shows_everything(): void
    {
        $this->shy->update(['is_public' => false]);

        // The browser under /tests is the tool for checking the data. A club that is invisible
        // there as well would be invisible to the people who have to correct it.
        $this->get(\App\Filament\Pages\Browse\Groups::getUrl())->assertOk()->assertSee('Fechtschule Musterstadt');
        $this->get(\App\Filament\Pages\Browse\Tournament::getUrl(['public_id' => $this->tournament->public_id]))
            ->assertOk()
            ->assertSee('Fechtschule Musterstadt');
    }
}
