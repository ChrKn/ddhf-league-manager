<?php

namespace App\Filament\Resources\Announcements\Tables;

use App\Models\Announcement;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AnnouncementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                // Computed rather than stored: whether the site still shows it follows from the
                // date alone, see Announcement::recent().
                IconColumn::make('shown')
                    ->label('Auf der Seite')
                    ->boolean()
                    ->state(fn (Announcement $record) => $record->isShown())
                    ->tooltip('Die Seite zeigt Meldungen der letzten ' . Announcement::SHOWN_FOR_MONTHS . ' Monate.'),
                TextColumn::make('title')
                    ->label('Überschrift')
                    ->searchable()
                    ->wrap(),
                IconColumn::make('translated')
                    ->label('Englisch')
                    ->boolean()
                    ->state(fn (Announcement $record) => $record->isTranslated('en')),
                TextColumn::make('author.name')
                    ->label('Autor')
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label('Erstellt am')
                    ->dateTime('d.m.Y H:i', config('site.timezone'))
                    ->sortable(),
                TextColumn::make('revised_at')
                    ->label('Aktualisiert am')
                    ->dateTime('d.m.Y H:i', config('site.timezone'))
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Zuletzt gespeichert')
                    ->dateTime('d.m.Y H:i', config('site.timezone'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
