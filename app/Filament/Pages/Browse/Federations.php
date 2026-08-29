<?php

namespace App\Filament\Pages\Browse;

use App\Browse\DataBrowser;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class Federations extends BrowsePage
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    protected static ?string $navigationLabel = 'Verbände';

    protected static ?string $slug = 'daten/verbaende';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.browse.table';

    protected function load(DataBrowser $browser): array
    {
        return $browser->federations(request());
    }
}
