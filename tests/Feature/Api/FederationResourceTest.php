<?php

namespace Tests\Feature\Api;

use App\Models\ApiKey;
use App\Models\Federation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A federation is not its country, and the two do not stand in for each other: one country can
 * hold several federations - Poland has two on record - so asking which country a club is in
 * cannot be answered by looking at its federation, nor the other way round.
 */
class FederationResourceTest extends TestCase
{
    use RefreshDatabase;

    private string $key;

    protected function setUp(): void
    {
        parent::setUp();

        $this->key = ApiKey::create(['name' => 'Test', 'key' => str_repeat('F', 64)])->key;
    }

    public function test_a_federation_reports_its_country_and_its_english_name(): void
    {
        $federation = Federation::create([
            'name'         => 'Ελληνικη Ομοσπονδια Ιστορικων Ευρωπαϊκων Πολεμικων Τεχνων',
            'english_name' => 'Greek Federation of Historical European Martial Arts',
            'abbreviation' => 'ΕΟΙΕΠΤ',
            'country'      => 'GR',
            'is_active'    => true,
        ]);

        $this->withToken($this->key)
            ->getJson("/api/federations/{$federation->public_id}")
            ->assertOk()
            ->assertJsonPath('data.country', 'GR')
            ->assertJsonPath('data.english_name', 'Greek Federation of Historical European Martial Arts');
    }

    public function test_a_federation_whose_own_name_already_reads_in_english_carries_none(): void
    {
        // The field is there for names that do not travel. Repeating "HEMA Ireland" in an
        // english_name column would say nothing, so it stays empty - and empty has to survive
        // the API rather than being dropped from the payload.
        $federation = Federation::create(['name' => 'HEMA Ireland', 'country' => 'IE', 'is_active' => true]);

        $this->withToken($this->key)
            ->getJson("/api/federations/{$federation->public_id}")
            ->assertOk()
            ->assertJsonPath('data.english_name', null)
            ->assertJsonPath('data.country', 'IE');
    }

    public function test_two_federations_may_share_a_country(): void
    {
        // The reason the country sits on the federation instead of being inferred from it: a
        // country can be organised more than once, by weapon or by history.
        Federation::create(['name' => 'Polska Federacja Dawnych Europejskich Sztuk Walki', 'country' => 'PL', 'is_active' => true]);
        Federation::create(['name' => 'Związek Sportowy Szermierki Historycznej', 'country' => 'PL', 'is_active' => true]);

        $this->assertSame(2, Federation::where('country', 'PL')->count());
    }
}
