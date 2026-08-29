<?php

namespace App\Filament\Resources\ResultImports\Tables;

use App\Filament\Resources\ResultImports\ResultImportResource;
use App\Import\ImportStatus;
use App\Models\ResultImport;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ResultImportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->label('Hochgeladen'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (ImportStatus $state): string => $state->label())
                    ->color(fn (ImportStatus $state): string => $state->color())
                    ->label('Stand'),
                TextColumn::make('sheets_count')
                    ->counts('sheets')
                    ->label('Dateien'),
                TextColumn::make('rows_count')
                    ->counts('rows')
                    ->label('Zeilen'),
                TextColumn::make('offen')
                    // Not a column and not worth one: it is a count of what has no decision yet,
                    // and it changes every time somebody makes one.
                    ->state(fn (ResultImport $record): int => $record->openRows())
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'warning' : 'success')
                    ->label('Offen'),
                TextColumn::make('user.name')
                    ->placeholder('Konsole')
                    ->toggleable()
                    ->label('Von'),
                TextColumn::make('applied_at')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable()
                    ->label('Übernommen'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(array_combine(
                        array_column(ImportStatus::cases(), 'value'),
                        array_map(fn (ImportStatus $case) => $case->label(), ImportStatus::cases()),
                    ))
                    ->label('Stand'),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Zuordnen')
                    ->visible(fn (ResultImport $record): bool => $record->status->isOpen()),
                Action::make('review')
                    ->label('Prüfen')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->url(fn (ResultImport $record): string => ResultImportResource::getUrl('review', ['record' => $record])),
                DeleteAction::make()
                    // An import that was written is the record of what happened. Deleting it
                    // would not take the results back out, only the account of where they came
                    // from.
                    ->visible(fn (ResultImport $record): bool => $record->status->isOpen()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Noch nichts importiert')
            ->emptyStateDescription('Ein Import nimmt die Ergebnisdateien einer Veranstaltung auf, '
                . 'gleicht Fechter und Vereine mit dem Bestand ab und legt daraus die Turniere an.');
    }
}
