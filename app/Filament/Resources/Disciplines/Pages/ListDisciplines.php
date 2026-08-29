<?php

namespace App\Filament\Resources\Disciplines\Pages;

use App\Filament\Exports\DisciplineExporter;
use App\Filament\Resources\Disciplines\DisciplineResource;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;

class ListDisciplines extends ListRecords
{
    protected static string $resource = DisciplineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            ExportAction::make()
                ->exporter(DisciplineExporter::class)
                ->columnMapping(false)
                ->label('Exportieren'),
        ];
    }
}
