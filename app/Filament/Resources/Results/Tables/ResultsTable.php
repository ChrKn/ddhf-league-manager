<?php

namespace App\Filament\Resources\Results\Tables;

use App\Models\Fencer;
use App\Models\Tournament;
use App\Standings\Placement;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ResultsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                // Both of these show an accessor rather than a column, so neither searching nor
                // sorting can be left to the column name - Filament would put "display_name"
                // straight into the SQL and the query fails on a column that does not exist.
                // Pointed at the real ones instead, the same way TournamentsTable already does.
                TextColumn::make('tournament.display_name')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'tournament',
                        function (Builder $tournament) use ($search): void {
                            // Everything the column puts on the screen: weapon, division, year and
                            // the event in brackets.
                            $tournament
                                ->whereHas('event', fn (Builder $event) => $event->where('name', 'like', "%{$search}%"))
                                ->orWhereHas('season.standing.discipline', fn (Builder $d) => $d->where('name', 'like', "%{$search}%"))
                                ->orWhereHas('season.standing.division', fn (Builder $d) => $d->where('name', 'like', "%{$search}%"));

                            // Only where it could be one. YEAR(x) = 'Berlin' is a comparison the
                            // database should never be asked to make.
                            if (preg_match('/^\d{4}$/', $search)) {
                                $tournament->orWhereHas('season', fn (Builder $s) => $s->where('year', $search));
                            }
                        },
                    ))
                    // By when it was fenced rather than by the text: the column reads
                    // "Langes Schwert offen 2026 (Dürer Turnier)", and sorting that alphabetically
                    // would group by weapon, which the discipline column next to it already does.
                    // Chronological is what puts one tournament's results together.
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy(
                        Tournament::select('events.start_date')
                            ->join('events', 'events.id', '=', 'tournaments.event_id')
                            ->whereColumn('tournaments.id', 'results.tournament_id'),
                        $direction,
                    ))
                    ->label('Turnier'),
                TextColumn::make('fencer.display_name')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'fencer',
                        // An anonymised record holds no name at all, so it matches nothing here -
                        // and the placeholder the column shows in its place is not searchable
                        // either, which is the point of deriving it rather than storing it.
                        fn (Builder $fencer) => $fencer
                            ->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhereRaw("CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) like ?", ["%{$search}%"]),
                    ))
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query
                        ->orderBy(Fencer::select('last_name')->whereColumn('fencers.id', 'results.fencer_id'), $direction)
                        ->orderBy(Fencer::select('first_name')->whereColumn('fencers.id', 'results.fencer_id'), $direction))
                    ->label('Fechter'),
                TextColumn::make('fencer_group_name')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Keine Angabe')
                    ->label('Gruppe'),
                TextColumn::make('placement')
                    ->sortable()
                    // "Achtelfinale" rather than "last-16". The stored value stays searchable, so
                    // looking for every pool exit still works by typing "pools".
                    ->formatStateUsing(fn (string $state) => Placement::describes($state)
                        ? Placement::from($state)->label()
                        : $state)
                    ->searchable()
                    ->label('Platz'),
                TextColumn::make('counted_on_request')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('—')
                    ->wrap()
                    ->label('Auf Antrag gewertet'),
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
                // Ausnahmen sind selten und sollen nachschlagbar bleiben - die Regel ist, dass
                // die Mitgliedschaft am Ergebnis entscheidet.
                Filter::make('counted_on_request')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('counted_on_request'))
                    ->label('Nur auf Antrag gewertete')
                    ->toggle(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
