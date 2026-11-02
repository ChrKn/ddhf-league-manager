<?php

namespace App\Filament\Resources\TournamentFormats\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TournamentFormatsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->reorderable('sort')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->label('Name'),
                TextColumn::make('tournaments_count')
                    ->counts('tournaments')
                    ->label('Turniere'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
