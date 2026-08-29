<?php

namespace App\Filament\Resources\Seasons\RelationManagers;

use App\Models\Fencer;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Who is ranked in this standing rather than in the other category of the same weapon.
 *
 * Only needed for the few who competed in both - `php artisan standings:division-choices` lists
 * them. Everybody else is ranked where they fenced and needs no entry here.
 *
 * This deliberately lives on the season and not on the fencer: the choice belongs to a season and
 * may differ from one to the next, and putting it on the person would make it look like a property
 * of the person, which is exactly what it is not.
 */
class FencersRelationManager extends RelationManager
{
    protected static string $relationship = 'fencers';

    protected static ?string $title = 'Gewertete Fechter';

    protected static ?string $modelLabel = 'Fechter';

    protected static ?string $pluralModelLabel = 'Fechter';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('last_name')
            ->defaultSort('last_name')
            ->columns([
                // display_name is an accessor, so the columns behind it have to be named for both
                // searching and sorting - the search already did, the sort did not and would
                // have failed on an unknown column the first time anybody clicked the header.
                TextColumn::make('display_name')
                    ->label('Fechter')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(['last_name', 'first_name']),
                TextColumn::make('group.name')
                    ->label('Verein')
                    ->placeholder('Kein Verein')
                    ->searchable()
                    ->sortable(),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Fechter eintragen')
                    ->recordSelectSearchColumns(['first_name', 'last_name'])
                    // The club belongs in the label: the people who need an entry here are the ones
                    // who fenced both categories of a weapon, and two of those can share a name.
                    ->recordTitle(fn (Fencer $record): string => $record->display_name_with_group)
                    // A custom title takes the select off the path that sorts by the title column,
                    // so the order has to be asked for here or the list arrives as the database
                    // happens to hold it.
                    ->recordSelectOptionsQuery(fn (Builder $query): Builder => $query
                        ->orderBy('last_name')
                        ->orderBy('first_name')),
            ])
            ->recordActions([
                DetachAction::make(),
            ])
            ->toolbarActions([
                DetachBulkAction::make(),
            ])
            ->emptyStateHeading('Niemand eingetragen')
            ->emptyStateDescription('Nur nötig, wer in beiden Kategorien dieser Waffe gefochten hat. '
                . 'Solange „Kategorie muss gewählt werden" aus ist, ändert ein Eintrag nichts.');
    }
}
