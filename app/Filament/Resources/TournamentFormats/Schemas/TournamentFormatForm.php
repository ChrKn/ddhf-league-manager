<?php

namespace App\Filament\Resources\TournamentFormats\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TournamentFormatForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(120)
                    ->unique(ignoreRecord: true)
                    ->label('Name')
                    ->helperText('So, wie es auf der Turnierseite stehen soll. Eine Umbenennung gilt für alle Turniere mit '
                        . 'diesem System.'),
                TextInput::make('sort')
                    ->integer()
                    ->minValue(0)
                    ->default(0)
                    ->label('Reihenfolge')
                    ->helperText('Kleinere Zahlen stehen in der Auswahl weiter oben.'),
            ]);
    }
}
