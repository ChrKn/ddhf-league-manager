<?php

namespace App\Filament\Resources\Groups\Pages;

use App\Filament\Exports\GroupExporter;
use App\Filament\Resources\Groups\GroupResource;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;

class ListGroups extends ListRecords
{
    protected static string $resource = GroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            ExportAction::make()
                ->exporter(GroupExporter::class)
                ->columnMapping(false)
                ->label('Exportieren'),
        ];
    }
}
