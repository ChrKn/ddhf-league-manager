<?php

namespace App\Filament\Resources\Tournaments\Pages;

use App\Filament\Exports\TournamentExporter;
use App\Filament\Resources\Tournaments\TournamentResource;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;

class ListTournaments extends ListRecords
{
    protected static string $resource = TournamentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            ExportAction::make()
                ->exporter(TournamentExporter::class)
                ->columnMapping(false)
                ->label('Exportieren'),
        ];
    }
}
