<?php

namespace App\Filament\Resources\Events\Pages;

use App\Filament\Exports\EventExporter;
use App\Filament\Resources\Events\EventResource;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;

class ListEvents extends ListRecords
{
    protected static string $resource = EventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            ExportAction::make()
                ->exporter(EventExporter::class)
                ->columnMapping(false)
                ->label('Exportieren'),
        ];
    }
}
