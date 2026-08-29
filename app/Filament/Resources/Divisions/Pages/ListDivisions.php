<?php

namespace App\Filament\Resources\Divisions\Pages;

use App\Filament\Exports\DivisionExporter;
use App\Filament\Resources\Divisions\DivisionResource;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;

class ListDivisions extends ListRecords
{
    protected static string $resource = DivisionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            ExportAction::make()
                ->exporter(DivisionExporter::class)
                ->columnMapping(false)
                ->label('Exportieren'),
        ];
    }
}
