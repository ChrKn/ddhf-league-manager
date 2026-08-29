<?php

namespace App\Filament\Resources\ApiKeys\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class ApiKeysTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ToggleColumn::make('is_active')
                    ->label('Aktiv'),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->label('Name'),
                // The first eight characters, which is all there is. Enough to match a row against
                // the key somebody is holding, useless to anybody who is not already holding it.
                TextColumn::make('key_prefix')
                    ->fontFamily(FontFamily::Mono)
                    ->formatStateUsing(fn (string $state) => $state . '…')
                    ->searchable()
                    ->label('API Schlüssel'),
                TextColumn::make('scope')
                    ->badge()
                    ->label('Zugriffsrechte'),
                TextColumn::make('last_used_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->label('Zuletzt verwendet am'),
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
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
