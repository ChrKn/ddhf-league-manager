<?php

namespace App\Filament\Resources\TournamentFormats\Pages;

use App\Filament\Resources\TournamentFormats\TournamentFormatResource;
use App\Filament\Support\GuardedDelete;
use Filament\Resources\Pages\EditRecord;

class EditTournamentFormat extends EditRecord
{
    protected static string $resource = TournamentFormatResource::class;

    protected function getHeaderActions(): array
    {
        return [
            GuardedDelete::make(),
        ];
    }
}
