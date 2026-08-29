<?php

namespace App\Filament\Resources\Groups\RelationManagers;

use App\Data\Countries;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The towns this club trains in.
 *
 * Several is normal - the Europäische Schwertkunst has seven - which is why this is a table of
 * its own and not a column on the club.
 */
class LocationsRelationManager extends RelationManager
{
    protected static string $relationship = 'locations';

    protected static ?string $title = 'Standorte';

    protected static ?string $modelLabel = 'Standort';

    protected static ?string $pluralModelLabel = 'Standorte';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('locality')
                ->label('Ort')
                ->helperText('Die Stadt, in der trainiert wird, z. B. "Bad Wildungen".')
                ->required()
                ->maxLength(255),
            TextInput::make('region')
                ->label('Land (Bundesland)')
                ->helperText('Bundesland, Kanton oder Provinz. Leer lassen, wo es keins gibt.')
                ->maxLength(255),
            Select::make('country')
                ->label('Land (Staat)')
                ->helperText('Normalerweise das des Vereins — ein Verein kann aber über die Grenze trainieren.')
                ->options(Countries::all())
                ->default(fn (): ?string => $this->getOwnerRecord()->country),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('locality')
            ->defaultSort('locality')
            ->columns([
                TextColumn::make('locality')
                    ->label('Ort')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('region')
                    ->label('Land (Bundesland)')
                    ->placeholder('Keine Angabe')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('country')
                    ->label('Land (Staat)')
                    ->placeholder('Keine Angabe')
                    ->sortable()
                    ->formatStateUsing(fn (?string $state): ?string => Countries::label($state)),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                // Unguarded on purpose, like the aliases next door: a training place is a club's
                // own subordinate data, nothing points at it, and one entered wrongly is a
                // correction rather than a hole. Without this the only way to take one back was
                // to tick it and go through the bulk menu.
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ])
            ->emptyStateHeading('Keine Standorte hinterlegt')
            ->emptyStateDescription('Für DDHF-Vereine steht der Trainingsort meist unter ddhf.de/mitglieder.');
    }
}
