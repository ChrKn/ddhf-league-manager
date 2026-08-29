<?php

namespace App\Filament\Resources\Standings\Pages;

use App\Filament\Resources\Standings\StandingResource;
use App\Filament\Support\GuardedDelete;
use Filament\Resources\Pages\EditRecord;

class EditStanding extends EditRecord
{
    protected static string $resource = StandingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            GuardedDelete::make(),
        ];
    }
}
