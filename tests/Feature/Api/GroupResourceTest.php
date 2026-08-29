<?php

namespace Tests\Feature\Api;

use App\Models\ApiKey;
use App\Models\Group;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Where a club is based is a detail of the club, not part of the list. The list endpoint answers
 * "which clubs are there" with an id and a name and nothing else, and that shape is what callers
 * page through - so the country belongs to /api/groups/{id} alone.
 */
class GroupResourceTest extends TestCase
{
    use RefreshDatabase;

    private string $key;

    protected function setUp(): void
    {
        parent::setUp();

        $this->key = ApiKey::create(['name' => 'Test', 'key' => str_repeat('L', 64)])->key;
    }

    public function test_a_club_reports_the_country_it_is_based_in(): void
    {
        $club = Group::create(['name' => 'INDES Salzburg', 'is_active' => true, 'country' => 'AT']);

        $this->withToken($this->key)
            ->getJson("/api/groups/{$club->public_id}")
            ->assertOk()
            ->assertJsonPath('data.country', 'AT');
    }

    public function test_a_club_of_unknown_whereabouts_reports_no_country_rather_than_omitting_it(): void
    {
        // Two thirds of the clubs on record are somewhere known and the rest are not. A field that
        // disappeared when empty would make the two cases indistinguishable from the outside.
        $club = Group::create(['name' => 'Walk The Path', 'is_active' => true]);

        $this->withToken($this->key)
            ->getJson("/api/groups/{$club->public_id}")
            ->assertOk()
            ->assertJsonPath('data.country', null);
    }

    public function test_the_club_list_stays_down_to_an_id_and_a_name(): void
    {
        Group::create(['name' => 'INDES Salzburg', 'is_active' => true, 'country' => 'AT']);

        $listed = $this->withToken($this->key)
            ->getJson('/api/groups')
            ->assertOk()
            ->json('data.0');

        $this->assertSame(['id', 'name'], array_keys($listed));
    }
}
