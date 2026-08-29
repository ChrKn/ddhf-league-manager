<?php

namespace App\Filament\Resources\ScoringMatrices\Schemas;

use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ScoringMatrixForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                CodeEditor::make('matrix')
                    ->language(Language::Json)
                    ->required(),
                TextInput::make('public_id')
                    ->disabled()
                    ->placeholder('Automatisch generiert')
                    ->label('ID'),
            ]);
    }
}
