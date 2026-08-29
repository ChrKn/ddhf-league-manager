<?php

namespace App\Filament\Resources\Fencers\Pages;

use App\Filament\Resources\Fencers\FencerResource;
use App\Filament\Support\GuardedDelete;
use Filament\Resources\Pages\EditRecord;

class EditFencer extends EditRecord
{
    protected static string $resource = FencerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            GuardedDelete::make(),
        ];
    }
}
