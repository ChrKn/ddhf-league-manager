<?php

namespace App\Filament\Resources\Standings\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class StandingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('discipline_id')
                    ->relationship('discipline', 'name')
                    ->label('Disziplin'),
                Select::make('division_id')
                    ->relationship('division', 'name')
                    ->label('Abteilung'),
                TextInput::make('public_id')
                    ->disabled()
                    ->placeholder('Automatisch generiert')
                    ->label('ID'),
            ]);
    }
}
