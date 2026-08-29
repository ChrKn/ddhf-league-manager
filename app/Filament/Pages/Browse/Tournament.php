<?php

namespace App\Filament\Pages\Browse;

use App\Browse\DataBrowser;
use Filament\Panel;

/**
 * One tournament with its whole field.
 *
 * Reached from the tournament list and from a standing, never from the navigation - there is no
 * such thing as "the" tournament to open.
 */
class Tournament extends BrowsePage
{
    // Singular, and not just for reading: the plural is the list page's slug, and
    // two pages sharing one would collide on the route name.
    protected static ?string $slug = 'daten/turnier';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.browse.tournament';

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
        return $browser->tournament($this->public_id);
    }
}
