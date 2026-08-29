<?php

namespace App\Filament\Resources\Events\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class EventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                Select::make('organizers')
                    ->label('Ausrichter')
                    ->relationship('organizers', 'name')
                    ->multiple(),
                DatePicker::make('start_date')
                    ->required()
                    ->label('Beginn'),
                DatePicker::make('end_date')
                    ->label('Ende'),
                TextInput::make('location')
                    ->label('Ort'),
                TextInput::make('public_id')
                    ->disabled()
                    ->placeholder('Automatisch generiert')
                    ->label('ID'),
            ]);
    }
}
