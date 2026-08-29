<?php

namespace App\Filament\Resources\Groups\Tables;

use App\Data\Countries;
use App\Models\Group;
use App\Models\GroupLocation;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class GroupsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ToggleColumn::make('is_active')
                    ->label('Aktiv'),
                ToggleColumn::make('is_public')
                    ->label('Sichtbar')
                    ->tooltip('Aus: der Name erscheint weder auf der Seite noch in der API, '
                        . 'auch nicht an den Fechtern. Ergebnisse und Punkte bleiben.'),
                TextColumn::make('public_id')
                    ->searchable()
                    ->label('ID'),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('abbreviation')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Keine Angabe')
                    ->label('Abkürzung'),
                // A club can be in a Dachverband and a Verbund at once, so this lists them
                // rather than picking one.
                TextColumn::make('federations.display_name')
                    ->badge()
                    // Searchable but deliberately not sortable: a club can be in several, and
                    // there is no honest answer to which of them a row should sort by.
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'federations',
                        fn (Builder $federation) => $federation
                            ->where('abbreviation', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%"),
                    ))
                    ->placeholder('Keine Angabe')
                    ->label('Verbände'),
                TextColumn::make('country')
                    ->sortable()
                    ->placeholder('Keine Angabe')
                    ->label('Land (Staat)')
                    ->formatStateUsing(fn (?string $state): ?string => Countries::label($state)),
                // A club can train in seven towns, so the column lists them rather than picking
                // one - and stays narrow until someone hovers it.
                TextColumn::make('locations.locality')
                    ->label('Standorte')
                    ->badge()
                    ->limitList(2)
                    ->expandableLimitedList()
                    ->searchable()
                    ->placeholder('Keine Angabe'),
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
                // Only the countries clubs are actually in, not all 250 of them.
                SelectFilter::make('country')
                    ->label('Land (Staat)')
                    ->options(fn (): array => Group::query()
                        ->whereNotNull('country')
                        ->distinct()
                        ->pluck('country')
                        ->mapWithKeys(fn (string $country): array => [$country => Countries::label($country)])
                        ->sort()
                        ->all()),
                // Only the states clubs actually train in. "Keine Angabe" is a real answer here:
                // finding the clubs nobody has looked up yet is the point of the column.
                SelectFilter::make('region')
                    ->label('Land (Bundesland)')
                    ->options(fn (): array => GroupLocation::query()
                        ->whereNotNull('region')
                        ->distinct()
                        ->orderBy('region')
                        ->pluck('region', 'region')
                        ->all())
                    ->query(fn ($query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn ($sub, $region) => $sub->whereHas('locations', fn ($l) => $l->where('region', $region))
                    )),
                TernaryFilter::make('hat_standort')
                    ->label('Standort hinterlegt')
                    ->placeholder('Alle')
                    ->trueLabel('Mit Standort')
                    ->falseLabel('Ohne Standort')
                    ->queries(
                        true: fn ($query) => $query->has('locations'),
                        false: fn ($query) => $query->doesntHave('locations'),
                        blank: fn ($query) => $query,
                    ),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
