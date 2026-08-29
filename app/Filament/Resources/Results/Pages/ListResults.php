<?php

namespace App\Filament\Resources\Results\Pages;

use App\Filament\Exports\ResultExporter;
use App\Filament\Resources\Results\ResultResource;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;

class ListResults extends ListRecords
{
    protected static string $resource = ResultResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            ExportAction::make()
                ->exporter(ResultExporter::class)
                ->columnMapping(false)
                ->label('Exportieren'),
        ];
    }
}
