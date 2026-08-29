<?php

namespace App\Filament\Pages\Browse;

use App\Browse\DataBrowser;
use Filament\Panel;

/**
 * The table of one season, built by the same calculator the public site and the API use.
 *
 * Reached from the standings list, so it registers no navigation entry of its own.
 */
class Ranking extends BrowsePage
{
    // Singular, and not just for reading: the plural is the list page's slug, and
    // two pages sharing one would collide on the route name.
    protected static ?string $slug = 'daten/rangliste';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.browse.ranking';

    public string $public_id = '';

    public static function getRoutePath(Panel $panel): string
    {
        return '/' . static::getSlug($panel) . '/{public_id}';
    }

    public function mount(string $public_id): void
    {
        $this->public_id = $public_id;
    }

    protected function load(DataBrowser $browser): array
    {
        return $browser->ranking($this->public_id);
    }
}
