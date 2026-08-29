<?php

namespace App\Filament\Pages\Browse;

use App\Browse\DataBrowser;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class Seasons extends BrowsePage
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?string $navigationLabel = 'Saisons';

    protected static ?string $slug = 'daten/saisons';

    protected static ?int $navigationSort = 7;

    protected string $view = 'filament.pages.browse.table';

    protected function load(DataBrowser $browser): array
    {
        return $browser->seasons(request());
    }
}
