<?php

namespace App\Filament\Pages\Browse;

use App\Browse\DataBrowser;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use UnitEnum;

/**
 * What every page of the data browser has in common.
 *
 * The browser used to be its own little site at /tests, with its own header, its own navigation
 * and no login at all - which meant a page showing every column the public site withholds
 * answered to anybody who knew the address. It is an admin's view, so it lives in the admin panel
 * and is reached the way everything else there is.
 *
 * What it is not is a set of Filament resources. Those show one record at a time with the columns
 * somebody chose; this shows every column of a whole table at once, which is the only way to scan
 * a season for the one placement that looks wrong. The tables stay plain HTML for that reason -
 * see resources/views/filament/pages/browse/chrome.blade.php.
 */
abstract class BrowsePage extends Page
{
    protected static string | UnitEnum | null $navigationGroup = 'Datenbestand';

    /** A table of everything is wide by definition, so it gets the whole window. */
    protected Width | string | null $maxContentWidth = Width::Full;

    /** Read once per request: the title and the body are the same query. */
    private ?array $data = null;

    /** Everything the page shows, including its own title. */
    abstract protected function load(DataBrowser $browser): array;

    public function getTitle(): string
    {
        return $this->data()['title'];
    }

    protected function getViewData(): array
    {
        return $this->data();
    }

    private function data(): array
    {
        return $this->data ??= $this->load(app(DataBrowser::class));
    }
}
