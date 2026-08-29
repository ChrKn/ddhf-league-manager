<?php

namespace App\Filament\Pages\Browse;

use App\Browse\DataBrowser;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class Events extends BrowsePage
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'Veranstaltungen';

    protected static ?string $slug = 'daten/veranstaltungen';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.browse.table';

    protected function load(DataBrowser $browser): array
    {
        return $browser->events(request());
    }
}
