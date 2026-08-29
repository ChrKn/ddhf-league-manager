<?php

namespace Tests\Feature\Groups;

use App\Models\ApiKey;
use App\Models\Group;
use App\Models\GroupLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Where a club trains is not the same question as which country it belongs to, and it does not
 * have one answer: five of the DDHF's members meet in more than one town.
 */
class LocationTest extends TestCase
{
    use RefreshDatabase;

    private string $key;

    protected function setUp(): void
    {
        parent::setUp();

        // The data browser is part of the admin panel now.
        $this->actingAsMaintainer();

        $this->key = ApiKey::create(['name' => 'Test', 'key' => str_repeat('S', 64)])->key;
    }

    private function club(string $name, array $towns, ?string $country = 'DE'): Group
    {
        $club = Group::create(['name' => $name, 'is_active' => true, 'country' => $country]);

        foreach ($towns as $locality => $region) {
            $club->locations()->create([
                'locality' => $locality,
                'region'   => $region,
                'country'  => $country,
            ]);
        }

        return $club->fresh();
    }

    public function test_a_club_can_train_in_several_towns(): void
    {
        $club = $this->club('Fechtschule Krîfon', [
            'Edingen' => 'Baden-Württemberg',
            'Koblenz' => 'Rheinland-Pfalz',
            'Worms'   => 'Rheinland-Pfalz',
        ]);

        $this->assertSame(
            ['Edingen', 'Koblenz', 'Worms'],
            $club->locations->pluck('locality')->all(),
        );
    }

    public function test_a_place_reads_as_town_and_state(): void
    {
        $club = $this->club('Leichtmeisterei', ['Kassel' => 'Hessen']);

        $this->assertSame('Kassel, Hessen', $club->locations->first()->display_name);
    }

    public function test_a_city_state_is_not_written_twice(): void
    {
        // Hamburg in Hamburg, Berlin in Berlin: naming both would read like a mistake.
        $club = $this->club('Hammaborg', ['Hamburg' => 'Hamburg']);

        $this->assertSame('Hamburg', $club->locations->first()->display_name);
    }

    public function test_a_place_may_sit_in_another_country_than_its_club(): void
    {
        // The Austrian INDES runs locations in Germany, and a club is free to do the same.
        $club = Group::create(['name' => 'INDES Wien', 'is_active' => true, 'country' => 'AT']);
        $club->locations()->create(['locality' => 'Regensburg', 'region' => 'Bayern', 'country' => 'DE']);

        $this->assertSame('AT', $club->country);
        $this->assertSame('DE', $club->locations->first()->country);
    }

    public function test_the_same_town_cannot_be_recorded_twice_for_one_club(): void
    {
        $club = $this->club('Stahlakademie', ['Leipzig' => 'Sachsen']);

        $this->expectException(\Illuminate\Database\QueryException::class);

        $club->locations()->create(['locality' => 'Leipzig', 'region' => 'Sachsen', 'country' => 'DE']);
    }

    public function test_deleting_a_club_takes_its_places_with_it(): void
    {
        $club = $this->club('Kielhau', ['Kiel' => 'Schleswig-Holstein']);

        $club->delete();

        $this->assertSame(0, GroupLocation::count());
    }

    public function test_the_api_answers_with_every_place_a_club_trains(): void
    {
        $club = $this->club('Historisches Schwertfechten Nordhessen', [
            'Bad Wildungen' => 'Hessen',
            'Kassel'        => 'Hessen',
        ]);

        $this->withToken($this->key)
            ->getJson("/api/groups/{$club->public_id}")
            ->assertOk()
            ->assertJsonPath('data.locations.0.locality', 'Bad Wildungen')
            ->assertJsonPath('data.locations.1.region', 'Hessen')
            ->assertJsonCount(2, 'data.locations');
    }

    public function test_a_club_nobody_looked_up_answers_with_an_empty_list_rather_than_nothing(): void
    {
        // An absent field and a club with no place on record would look the same from outside,
        // and only one of them is a question worth chasing.
        $club = $this->club('Walk The Path', []);

        $this->withToken($this->key)
            ->getJson("/api/groups/{$club->public_id}")
            ->assertOk()
            ->assertJsonPath('data.locations', []);
    }

    public function test_the_browser_lists_the_places_and_can_filter_by_state(): void
    {
        $this->club('Stahlakademie', ['Leipzig' => 'Sachsen']);
        $this->club('Kielhau', ['Kiel' => 'Schleswig-Holstein']);
        $this->club('Walk The Path', []);

        // Through route() rather than a typed path, so that moving the browser does not break a
        // test about club locations.
        $this->get(\App\Filament\Pages\Browse\Groups::getUrl())->assertOk()->assertSee('Leipzig, Sachsen')->assertSee('Kiel');

        $this->get(\App\Filament\Pages\Browse\Groups::getUrl(['region' => 'Sachsen']))
            ->assertOk()
            ->assertSee('Stahlakademie')
            ->assertDontSee('Kielhau');

        // The clubs nobody has looked up are the point of the filter.
        $this->get(\App\Filament\Pages\Browse\Groups::getUrl(['region' => 'none']))
            ->assertOk()
            ->assertSee('Walk The Path')
            ->assertDontSee('Stahlakademie');
    }
}
