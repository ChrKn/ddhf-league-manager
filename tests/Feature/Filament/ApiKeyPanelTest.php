<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\ApiKeys\Pages\CreateApiKey;
use App\Filament\Resources\ApiKeys\Pages\EditApiKey;
use App\Filament\Resources\ApiKeys\Pages\ListApiKeys;
use App\Models\ApiKey;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Handing a key out, in a panel that cannot look one up.
 *
 * The awkward part of storing only a digest is not the storing, it is the one moment afterwards
 * where somebody has to be given something to copy. These go through Livewire because that moment
 * is entirely a matter of pages passing the key between them.
 */
class ApiKeyPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::create([
            'name'     => 'Prüferin',
            'email'    => 'pruefung@example.test',
            'password' => 'geheim',
        ]));
    }

    public function test_creating_a_key_shows_it_on_the_page_it_lands_on(): void
    {
        Livewire::test(CreateApiKey::class)
            ->fillForm(['name' => 'Auswertung', 'scope' => 'read', 'is_active' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        $record = ApiKey::sole();

        // The create page has the key and no screen left to show it on, so it redirects here and
        // hands it over on the way.
        $fresh = Livewire::test(EditApiKey::class, ['record' => $record->getKey()])
            ->get('freshKey');

        $this->assertMatchesRegularExpression('/^[A-Z2-9]{64}$/', $fresh);
        $this->assertSame($record->key_prefix, substr($fresh, 0, 8));

        $this->withToken($fresh)->getJson('/api/federations')->assertOk();
    }

    public function test_coming_back_to_the_page_shows_no_key(): void
    {
        Livewire::test(CreateApiKey::class)
            ->fillForm(['name' => 'Auswertung', 'scope' => 'read', 'is_active' => true])
            ->call('create');

        $record = ApiKey::sole();

        Livewire::test(EditApiKey::class, ['record' => $record->getKey()]);

        // Second visit. Nothing to take from the session and nothing in the record either, which
        // is what makes "shown once" true rather than merely intended.
        Livewire::test(EditApiKey::class, ['record' => $record->getKey()])
            ->assertSet('freshKey', null);
    }

    public function test_the_list_shows_the_beginning_of_a_key_and_not_the_key(): void
    {
        $record = ApiKey::create(['name' => 'Auswertung']);

        Livewire::test(ListApiKeys::class)
            ->assertCanSeeTableRecords([$record])
            ->assertCanRenderTableColumn('key_prefix')
            ->assertSee($record->key_prefix)
            ->assertDontSee($record->key);
    }

    public function test_the_form_offers_the_beginning_of_the_key_and_not_the_key(): void
    {
        $record = ApiKey::create(['name' => 'Auswertung']);

        Livewire::test(EditApiKey::class, ['record' => $record->getKey()])
            ->assertFormSet(['key_prefix' => $record->key_prefix . '…'])
            ->assertDontSee($record->key);
    }

    public function test_a_lost_key_is_replaced_from_the_edit_page(): void
    {
        $record = ApiKey::create(['name' => 'Auswertung']);
        $lost = $record->key;

        $page = Livewire::test(EditApiKey::class, ['record' => $record->getKey()])
            ->callAction('regenerate')
            ->assertNotified();

        $fresh = $page->get('freshKey');

        $this->assertNotSame($lost, $fresh);
        $this->assertSame($record->refresh()->key_prefix, substr($fresh, 0, 8));

        $this->withToken($fresh)->getJson('/api/federations')->assertOk();
        $this->withToken($lost)->getJson('/api/federations')->assertUnauthorized();
    }

    public function test_the_new_key_replaces_the_old_one_on_the_screen_too(): void
    {
        $record = ApiKey::create(['name' => 'Auswertung']);
        $before = $record->key_prefix;

        // The form was filled before the key changed. Leaving it would show the old beginning
        // beside the new key.
        Livewire::test(EditApiKey::class, ['record' => $record->getKey()])
            ->callAction('regenerate')
            ->assertFormSet(fn (array $state) => $state['key_prefix'] !== $before . '…');
    }
}
