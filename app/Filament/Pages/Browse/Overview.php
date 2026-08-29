<?php

namespace App\Filament\Pages\Browse;

use App\Browse\DataBrowser;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class Overview extends BrowsePage
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?string $navigationLabel = 'Übersicht';

    protected static ?string $slug = 'daten';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.browse.overview';

    protected function load(DataBrowser $browser): array
    {
        return $browser->index();
    }
}
