<?php

namespace Tests\Feature\Api;

use App\Models\ApiKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The door to /api, and what is behind the lock.
 *
 * Two claims are made about api_keys and both are worth holding onto: nothing in the table can be
 * turned back into a working key, and a request gets in with an ordinary bearer token rather than a
 * header of our own invention.
 */
class ApiKeyTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'PROBESCHLUESSEL2345678PROBESCHLUESSEL2345678PROBESCHLUESSEL234567';

    private function key(array $attributes = []): ApiKey
    {
        return ApiKey::create(array_merge(['name' => 'Test', 'key' => self::KEY], $attributes));
    }

    public function test_the_key_itself_is_nowhere_in_the_table(): void
    {
        $this->key();

        $stored = (array) DB::table('api_keys')->sole();

        // Not under any column, whole or in part beyond the prefix that is there on purpose.
        foreach ($stored as $column => $value) {
            if ($column !== 'key_prefix') {
                $this->assertNotSame(self::KEY, (string) $value);
                $this->assertStringNotContainsString(substr(self::KEY, 20, 20), (string) $value);
            }
        }

        $this->assertSame('PROBESCH', $stored['key_prefix']);
        $this->assertSame(ApiKey::digest(self::KEY), $stored['key_hash']);
    }

    public function test_a_key_read_back_from_the_database_no_longer_knows_itself(): void
    {
        $created = $this->key();

        // The instance that made it still has it - that is the one chance to hand it over.
        $this->assertSame(self::KEY, $created->key);

        $this->assertNull(ApiKey::sole()->key);
    }

    public function test_the_digest_never_leaves_through_a_payload(): void
    {
        $this->assertArrayNotHasKey('key_hash', $this->key()->toArray());
    }

    public function test_a_bearer_token_opens_the_api(): void
    {
        $this->key();

        $this->withToken(self::KEY)->getJson('/api/federations')->assertOk();
    }

    public function test_a_request_without_a_token_is_told_which_scheme_was_wanted(): void
    {
        $this->key();

        // Naming the scheme is the whole reason for moving off a header of our own: a client that
        // has never heard of this API can read the refusal and know what to send.
        $this->getJson('/api/federations')
            ->assertUnauthorized()
            ->assertHeader('WWW-Authenticate', 'Bearer realm="DDHF Ranglisten"');
    }

    public function test_the_header_this_replaced_no_longer_opens_anything(): void
    {
        $this->key();

        $this->withHeader('X-API-Key', self::KEY)
            ->getJson('/api/federations')
            ->assertUnauthorized();
    }

    public function test_a_wrong_token_is_refused(): void
    {
        $this->key();

        $this->withToken(str_repeat('X', 64))->getJson('/api/federations')->assertUnauthorized();
    }

    public function test_a_switched_off_key_is_refused(): void
    {
        $this->key(['is_active' => false]);

        $this->withToken(self::KEY)->getJson('/api/federations')->assertUnauthorized();
    }

    public function test_using_a_key_is_not_a_change_to_it(): void
    {
        $key = $this->key();
        $changedAt = $key->updated_at;

        $this->travel(1)->hours();
        $this->withToken(self::KEY)->getJson('/api/federations')->assertOk();

        $key->refresh();

        $this->assertNotNull($key->last_used_at);
        // Left alone, updated_at would become a copy of last_used_at and stop saying when somebody
        // last touched the key's name, scope or switch.
        $this->assertTrue($changedAt->equalTo($key->updated_at));
    }

    public function test_a_new_key_replaces_the_one_that_was_lost(): void
    {
        $key = $this->key();

        $fresh = $key->regenerate();

        $this->assertNotSame(self::KEY, $fresh);
        $this->assertSame(substr($fresh, 0, 8), $key->refresh()->key_prefix);

        $this->withToken($fresh)->getJson('/api/federations')->assertOk();
        $this->withToken(self::KEY)->getJson('/api/federations')->assertUnauthorized();
    }

    public function test_a_key_nobody_supplied_is_made_up_on_the_spot(): void
    {
        $key = ApiKey::create(['name' => 'Ohne Vorgabe']);

        $this->assertMatchesRegularExpression('/^[A-Z2-9]{64}$/', $key->key);
        $this->assertSame(substr($key->key, 0, 8), $key->key_prefix);

        $this->withToken($key->key)->getJson('/api/federations')->assertOk();
    }

    public function test_two_keys_made_in_a_row_are_not_the_same(): void
    {
        $this->assertNotSame(
            ApiKey::create(['name' => 'Eins'])->key,
            ApiKey::create(['name' => 'Zwei'])->key,
        );
    }
}
