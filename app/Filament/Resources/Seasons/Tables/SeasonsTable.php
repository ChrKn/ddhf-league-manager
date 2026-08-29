<?php

namespace App\Filament\Resources\Seasons\Tables;

use App\Models\Standing;
use App\Standings\ScoringMode;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SeasonsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('public_id')
                    ->searchable()
                    ->label('ID'),
                // "Langes Schwert offen" - an accessor built from two tables, so both searching
                // and sorting have to be pointed at the columns underneath.
                TextColumn::make('standing.display_name')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'standing',
                        fn (Builder $standing) => $standing
                            ->whereHas('discipline', fn (Builder $d) => $d->where('name', 'like', "%{$search}%"))
                            ->orWhereHas('division', fn (Builder $d) => $d->where('name', 'like', "%{$search}%")),
                    ))
                    // Weapon first, then division - the order the column reads in.
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query
                        ->orderBy(Standing::select('disciplines.name')
                            ->join('disciplines', 'disciplines.id', '=', 'standings.discipline_id')
                            ->whereColumn('standings.id', 'seasons.standing_id'), $direction)
                        ->orderBy(Standing::select('divisions.name')
                            ->join('divisions', 'divisions.id', '=', 'standings.division_id')
                            ->whereColumn('standings.id', 'seasons.standing_id'), $direction))
                    ->label('Rangliste'),
                TextColumn::make('scoring_matrix.name')
                    ->label('Punkteschlüssel'),
                TextColumn::make('scoring_mode')
                    ->formatStateUsing(fn(?ScoringMode $state) => $state?->label())
                    ->label('Auswertungssystem'),
                TextColumn::make('year')
                    ->sortable()
                    ->label('Jahr'),
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
            ->defaultSort('year', 'desc')
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
