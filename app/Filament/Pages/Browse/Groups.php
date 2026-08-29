<?php

namespace App\Filament\Pages\Browse;

use App\Browse\DataBrowser;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class Groups extends BrowsePage
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?string $navigationLabel = 'Vereine';

    protected static ?string $slug = 'daten/vereine';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.browse.table';

    protected function load(DataBrowser $browser): array
    {
        return $browser->groups(request());
    }
}
