<?php

namespace Tests\Feature\Browse;

use App\Filament\Pages\Browse\Events;
use App\Filament\Pages\Browse\Federations;
use App\Filament\Pages\Browse\Fencers;
use App\Filament\Pages\Browse\Groups;
use App\Filament\Pages\Browse\Overview;
use App\Filament\Pages\Browse\Ranking;
use App\Filament\Pages\Browse\Seasons;
use App\Filament\Pages\Browse\Standings;
use App\Filament\Pages\Browse\Tournament;
use App\Filament\Pages\Browse\Tournaments;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The door the data browser never had.
 *
 * It used to answer at /tests with no middleware at all: a standing there shows an anonymised
 * fencer's rank, and every tournament page shows its full field, so walking a season rebuilt the
 * very list the public standing withholds. That was harmless only for as long as nothing was
 * deployed.
 *
 * Moving it into the admin panel is what fixed it, and this is the test that says so - every page
 * of it, so a new one cannot be added outside the panel by accident.
 */
class BrowserNeedsALoginTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The pages by class rather than by address.
     *
     * A data provider runs before the application boots, so getUrl() cannot be called out here -
     * it needs the panel, and the panel needs a container.
     *
     * @return array<string, array{class-string<\Filament\Pages\Page>}>
     */
    public static function pages(): array
    {
        return [
            'Übersicht'       => [Overview::class],
            'Verbände'        => [Federations::class],
            'Vereine'         => [Groups::class],
            'Fechter'         => [Fencers::class],
            'Veranstaltungen' => [Events::class],
            'Turniere'        => [Tournaments::class],
            'Saisons'         => [Seasons::class],
            'Ranglisten'      => [Standings::class],
            'ein Turnier'     => [Tournament::class],
            'eine Rangliste'  => [Ranking::class],
        ];
    }

    #[DataProvider('pages')]
    public function test_a_visitor_with_no_account_is_sent_to_the_login(string $page): void
    {
        // The detail pages are asked for a record that does not exist, on purpose: a 404 would
        // mean the page had already looked, and looking is what the login is there to prevent.
        $parameters = in_array($page, [Tournament::class, Ranking::class], true)
            ? ['public_id' => 'EGAL']
            : [];

        $this->get($page::getUrl($parameters, isAbsolute: false))
            ->assertRedirect(filament()->getLoginUrl());
    }

    public function test_the_address_it_used_to_answer_at_is_gone(): void
    {
        foreach (['/tests', '/tests/vereine', '/tests/fechter', '/tests/ranglisten'] as $old) {
            $this->get($old)->assertNotFound();
        }
    }

    public function test_somebody_with_an_account_gets_in(): void
    {
        $this->actingAsMaintainer();

        $this->get(Overview::getUrl(isAbsolute: false))->assertOk();
    }
}
