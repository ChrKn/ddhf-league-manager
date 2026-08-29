<?php

namespace App\Filament\Pages\Browse;

use App\Browse\DataBrowser;
use BackedEnum;
use Filament\Support\Icons\Heroicon;

class Standings extends BrowsePage
{
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedListBullet;

    protected static ?string $navigationLabel = 'Ranglisten';

    protected static ?string $slug = 'daten/ranglisten';

    protected static ?int $navigationSort = 8;

    protected string $view = 'filament.pages.browse.standings';

    protected function load(DataBrowser $browser): array
    {
        return $browser->standings();
    }
}
