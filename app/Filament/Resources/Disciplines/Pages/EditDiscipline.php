<?php

namespace App\Filament\Resources\Disciplines\Pages;

use App\Filament\Resources\Disciplines\DisciplineResource;
use App\Filament\Support\GuardedDelete;
use Filament\Resources\Pages\EditRecord;

class EditDiscipline extends EditRecord
{
    protected static string $resource = DisciplineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            GuardedDelete::make(),
        ];
    }
}
