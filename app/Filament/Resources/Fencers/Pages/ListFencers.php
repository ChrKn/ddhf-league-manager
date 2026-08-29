<?php

namespace App\Filament\Resources\Fencers\Pages;

use App\Filament\Exports\FencerExporter;
use App\Filament\Resources\Fencers\FencerResource;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;

class ListFencers extends ListRecords
{
    protected static string $resource = FencerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            ExportAction::make()
                ->exporter(FencerExporter::class)
                ->columnMapping(false)
                ->label('Exportieren'),
        ];
    }
}
