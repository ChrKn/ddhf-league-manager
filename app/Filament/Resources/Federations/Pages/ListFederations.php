<?php

namespace App\Filament\Resources\Federations\Pages;

use App\Filament\Exports\FederationExporter;
use App\Filament\Resources\Federations\FederationResource;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;

class ListFederations extends ListRecords
{
    protected static string $resource = FederationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            ExportAction::make()
                ->exporter(FederationExporter::class)
                ->columnMapping(false)
                ->label('Exportieren'),
        ];
    }
}
