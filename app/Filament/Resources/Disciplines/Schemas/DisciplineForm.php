<?php

namespace App\Filament\Resources\Disciplines\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class DisciplineForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('public_id')
                    ->disabled()
                    ->placeholder('Automatisch generiert')
                    ->label('ID'),
            ]);
    }
}
