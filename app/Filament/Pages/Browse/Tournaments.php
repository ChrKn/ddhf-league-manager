<?php

namespace App\Filament\Pages\Browse;

use App\Browse\DataBrowser;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class Tournaments extends BrowsePage
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedTrophy;

    protected static ?string $navigationLabel = 'Turniere';

    protected static ?string $slug = 'daten/turniere';

    protected static ?int $navigationSort = 6;

    protected string $view = 'filament.pages.browse.table';

    protected function load(DataBrowser $browser): array
    {
        return $browser->tournaments(request());
    }
}
