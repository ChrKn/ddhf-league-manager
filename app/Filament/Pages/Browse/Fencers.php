<?php

namespace App\Filament\Pages\Browse;

use App\Browse\DataBrowser;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class Fencers extends BrowsePage
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $navigationLabel = 'Fechter';

    protected static ?string $slug = 'daten/fechter';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.pages.browse.table';

    protected function load(DataBrowser $browser): array
    {
        return $browser->fencers(request());
    }
}
