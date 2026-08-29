<?php

namespace App\Filament\Resources\Events\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('public_id')
                    ->searchable()
                    ->label('ID'),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('start_date')
                    ->date('Y-m-d')
                    ->label('Beginn')
                    ->sortable(),
                TextColumn::make('end_date')
                    ->date('Y-m-d')
                    ->placeholder('Keine Angabe')
                    ->label('Ende')
                    ->sortable(),
                TextColumn::make('location')
                    ->searchable()
                    ->placeholder('Keine Angabe')
                    ->label('Ort')
                    ->toggleable(),
                TextColumn::make('organizers.name')
                    ->placeholder('Keine Angabe')
                    ->label('Ausrichter')
                    ->badge()
                    ->separator()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime('y-m-d h:i:s')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->label('Erstellt am'),
                TextColumn::make('updated_at')
                    ->dateTime('y-m-d h:i:s')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->label('Aktualisiert am'),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
