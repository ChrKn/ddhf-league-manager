<?php

namespace App\Filament\Resources\Tournaments\Tables;

use App\Models\Event;
use App\Models\Season;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TournamentsTable
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
                    ->sortable()
                    ->placeholder('Keine Angabe')
                    ->label('Name'),
                // display_name is an accessor - "Dürer Turnier (2019)", built from the name and
                // the year - so neither searching nor sorting can be left to the column name.
                // Filament would put it straight into the SQL and the query fails on an unknown
                // column. Both are pointed at the real ones instead.
                TextColumn::make('event.display_name')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'event',
                        function (Builder $event) use ($search): void {
                            $event->where('name', 'like', "%{$search}%");

                            // The year is on show in this column, so it is worth being able to
                            // type it. Only for something that could be one - YEAR(x) = 'Berlin'
                            // is a comparison the database should never be asked to make.
                            if (preg_match('/^\d{4}$/', $search)) {
                                $event->orWhereYear('start_date', $search);
                            }
                        },
                    ))
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy(
                        Event::select('name')->whereColumn('events.id', 'tournaments.event_id'),
                        $direction,
                    ))
                    ->label('Veranstaltung'),
                // Same accessor problem as the column above, one relation further out:
                // "Langes Schwert offen 2026" comes from three tables.
                TextColumn::make('season.display_name')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'season',
                        function (Builder $season) use ($search): void {
                            $season
                                ->whereHas('standing.discipline', fn (Builder $d) => $d->where('name', 'like', "%{$search}%"))
                                ->orWhereHas('standing.division', fn (Builder $d) => $d->where('name', 'like', "%{$search}%"));

                            if (preg_match('/^\d{4}$/', $search)) {
                                $season->orWhere('year', $search);
                            }
                        },
                    ))
                    // By year. In a list of tournaments, "sort by season" is a question about
                    // when, and the discipline column beside this one already groups by weapon.
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy(
                        Season::select('year')->whereColumn('seasons.id', 'tournaments.season_id'),
                        $direction,
                    ))
                    ->label('Saison'),
                TextColumn::make('participant_count')
                    ->label('Teilnehmer'),
                TextColumn::make('ruleset.name')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Keine Angabe')
                    ->toggleable()
                    ->label('Regelwerk'),
                TextColumn::make('region')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Keine Angabe')
                    ->toggleable()
                    ->label('Region')
                    ->formatStateUsing(function ($state) {
                        return match ($state) {
                            'north' => 'Norden',
                            'east' => 'Osten',
                            'south' => 'Süden',
                            'west' => 'Westen',
                            default => 'Fehlerhafte Daten',
                        };
                    }),
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
