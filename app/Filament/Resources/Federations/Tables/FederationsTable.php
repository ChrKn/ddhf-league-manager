<?php

namespace App\Filament\Resources\Federations\Tables;

use App\Data\Countries;
use App\Models\Federation;
use Filament\Actions\EditAction;
use App\Federations\FederationKind;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class FederationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ToggleColumn::make('is_active')
                    ->label('Aktiv'),
                ToggleColumn::make('is_public')
                    ->label('Sichtbar')
                    ->tooltip('Aus: der Name erscheint weder auf der Seite noch in der API. '
                        . 'Auf die Wertung hat es keinen Einfluss.'),
                TextColumn::make('public_id')
                    ->searchable()
                    ->label('ID'),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                // A Verbund is not a Dachverband and decides nothing about a standing, which is
                // easier to keep straight when the list says so.
                TextColumn::make('kind')
                    ->badge()
                    ->sortable()
                    ->formatStateUsing(fn (FederationKind $state): string => $state->label())
                    ->color(fn (FederationKind $state): string => $state === FederationKind::National ? 'primary' : 'gray')
                    ->label('Art'),
                TextColumn::make('english_name')
                    ->searchable()
                    ->toggleable()
                    ->placeholder('Keine Angabe')
                    ->label('Englischer Name'),
                TextColumn::make('abbreviation')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Keine Angabe')
                    ->label('Abkürzung'),
                TextColumn::make('country')
                    ->sortable()
                    ->placeholder('Keine Angabe')
                    ->label('Land (Staat)')
                    ->formatStateUsing(fn (?string $state): ?string => Countries::label($state)),
                TextColumn::make('website_url')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('Keine Angabe')
                    ->label('Website URL'),
                TextColumn::make('logo_url')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('Keine Angabe')
                    ->label('Logo URL'),
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
                // Only the countries federations are actually in. More than one may share a
                // country, which is the point of the column.
                SelectFilter::make('country')
                    ->label('Land (Staat)')
                    ->options(fn (): array => Federation::query()
                        ->whereNotNull('country')
                        ->distinct()
                        ->pluck('country')
                        ->mapWithKeys(fn (string $country): array => [$country => Countries::label($country)])
                        ->sort()
                        ->all()),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
