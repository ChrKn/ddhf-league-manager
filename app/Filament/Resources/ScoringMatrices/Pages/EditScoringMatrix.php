<?php

namespace App\Filament\Resources\ScoringMatrices\Pages;

use App\Filament\Resources\ScoringMatrices\ScoringMatrixResource;
use App\Filament\Support\GuardedDelete;
use Filament\Resources\Pages\EditRecord;

class EditScoringMatrix extends EditRecord
{
    protected static string $resource = ScoringMatrixResource::class;

    protected function getHeaderActions(): array
    {
        return [
            GuardedDelete::make(),
        ];
    }
}
