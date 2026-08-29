<?php

namespace App\Filament\Resources\Fencers\Tables;

use App\Data\Countries;
use App\Filament\Resources\Fencers\Actions\AnonymizeFencerAction;
use App\Models\Fencer;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class FencersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ToggleColumn::make('is_active')
                    ->label('Aktiv'),
                TextColumn::make('public_id')
                    ->searchable()
                    ->label('ID'),
                TextColumn::make('title')
                    ->sortable()
                    ->label('Titel')->formatStateUsing(function ($state) {
                        return match ($state) {
                            'doctor' => 'Dr.',
                            'professor' => 'Prof. Dr.',
                        };
                    }),
                // Empty for an anonymised record, because there is nothing stored any more. The
                // placeholder is shown once, on the surname, rather than split across both
                // columns - "Anonymer" as a first name would look like a name again.
                TextColumn::make('first_name')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->label('Vorname'),
                TextColumn::make('last_name')
                    ->searchable()
                    ->sortable()
                    ->placeholder(Fencer::ANONYMOUS_NAME)
                    ->label('Nachname'),
                TextColumn::make('birth_name')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Keine Angabe')
                    ->label('Geburtsname'),
                TextColumn::make('nationality')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Keine Angabe')
                    ->label('Nationalität (Staat)')
                    ->toggleable()
                    ->formatStateUsing(fn(?string $state): ?string => Countries::label($state)),
                TextColumn::make('group.name')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Keine Angabe')
                    ->toggleable()
                    ->label('Gruppe'),
                TextColumn::make('date_of_birth')
                    ->date('Y-m-d')
                    ->sortable()
                    ->placeholder('Keine Angabe')
                    ->toggleable()
                    ->label('Geburtsdatum'),
                TextColumn::make('gender')
                    ->sortable()
                    ->placeholder('Keine Angabe')
                    ->formatStateUsing(function ($state) {
                        return match ($state) {
                            'male' => 'männlich',
                            'female' => 'weiblich',
                            'non_binary' => 'Nicht binär',
                            default => 'Fehlerhafte Daten',
                        };
                    })
                    ->label('Geschlecht')
                    ->toggleable(),
                IconColumn::make('anonymized_at')
                    ->label('Anonymisiert')
                    ->boolean()
                    ->trueIcon(Heroicon::OutlinedUserMinus)
                    ->falseIcon(null)
                    ->trueColor('danger')
                    ->tooltip(fn ($record) => $record->anonymized_at?->format('d.m.Y'))
                    ->toggleable(),
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
                TernaryFilter::make('anonymized_at')
                    ->label('Anonymisiert')
                    ->placeholder('Alle')
                    ->trueLabel('Nur anonymisierte')
                    ->falseLabel('Ohne anonymisierte')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('anonymized_at'),
                        false: fn ($query) => $query->whereNull('anonymized_at'),
                        blank: fn ($query) => $query,
                    ),
            ])
            ->recordActions([
                EditAction::make(),
                AnonymizeFencerAction::make(),
            ]);
    }
}
