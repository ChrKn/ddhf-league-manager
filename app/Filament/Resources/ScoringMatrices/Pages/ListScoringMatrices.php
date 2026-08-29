<?php

namespace App\Filament\Resources\ScoringMatrices\Pages;

use App\Filament\Exports\ScoringMatrixExporter;
use App\Filament\Resources\ScoringMatrices\ScoringMatrixResource;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;

class ListScoringMatrices extends ListRecords
{
    protected static string $resource = ScoringMatrixResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            ExportAction::make()
                ->exporter(ScoringMatrixExporter::class)
                ->columnMapping(false)
                ->label('Exportieren'),
        ];
    }
}
