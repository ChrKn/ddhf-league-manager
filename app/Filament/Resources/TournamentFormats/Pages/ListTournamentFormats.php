<?php

namespace App\Filament\Resources\TournamentFormats\Pages;

use App\Filament\Resources\TournamentFormats\TournamentFormatResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTournamentFormats extends ListRecords
{
    protected static string $resource = TournamentFormatResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
