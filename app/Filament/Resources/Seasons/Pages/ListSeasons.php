<?php

namespace App\Filament\Resources\Seasons\Pages;

use App\Filament\Exports\SeasonExporter;
use App\Filament\Resources\Seasons\SeasonResource;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;

class ListSeasons extends ListRecords
{
    protected static string $resource = SeasonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            ExportAction::make()
                ->exporter(SeasonExporter::class)
                ->columnMapping(false)
                ->label('Exportieren'),
        ];
    }
}
