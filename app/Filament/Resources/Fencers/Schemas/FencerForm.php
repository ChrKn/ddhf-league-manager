<?php

namespace App\Filament\Resources\Fencers\Schemas;

use App\Data\Countries;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class FencerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Toggle::make('is_active')
                    ->default(true)
                    ->label('Aktiv'),
                Select::make('title')
                    ->options(['doctor' => 'Dr.', 'professor' => 'Prof. Dr.']),
                // An anonymised record has no name and must not get one back through this form,
                // so the fields are locked rather than merely left empty - saving one would
                // otherwise fail on the required rule for no reason a reader could guess.
                TextInput::make('first_name')
                    ->required(fn ($record) => !$record?->isAnonymized())
                    ->disabled(fn ($record) => (bool) $record?->isAnonymized())
                    ->placeholder(fn ($record) => $record?->isAnonymized() ? 'Gelöscht' : null)
                    ->label('Vorname'),
                TextInput::make('last_name')
                    ->required(fn ($record) => !$record?->isAnonymized())
                    ->disabled(fn ($record) => (bool) $record?->isAnonymized())
                    ->placeholder(fn ($record) => $record?->isAnonymized() ? 'Gelöscht' : null)
                    ->label('Nachname'),
                TextInput::make('birth_name')
                    ->label('Geburtsname'),
                Select::make('nationality')
                    ->options(Countries::all())
                    ->default('DE')
                    ->label('Nationalität (Staat)'),
                Select::make('group_id')
                    ->relationship('group', 'name')
                    ->label('Gruppe'),
                DatePicker::make('date_of_birth')
                    ->label('Geburtsdatum'),
                Select::make('gender')
                    ->options([
                        'male' => 'männlich',
                        'female' => 'weiblich',
                        'non_binary' => 'Nicht binär'
                    ])
                    ->nullable(),
                TextInput::make('public_id')
                    ->disabled()
                    ->placeholder('Automatisch generiert')
                    ->label('ID'),
                // Set only by the Anonymisieren action, never by hand: the flag has to mean that
                // the personal data really is gone.
                DateTimePicker::make('anonymized_at')
                    ->disabled()
                    ->placeholder('Nicht anonymisiert')
                    ->helperText('Wird ausschließlich über die Aktion „Anonymisieren“ gesetzt.')
                    ->label('Anonymisiert am'),
                Textarea::make('anonymization_reason')
                    ->disabled()
                    ->visible(fn ($record) => $record?->isAnonymized())
                    ->rows(2)
                    ->label('Grund der Anonymisierung'),
            ]);
    }
}
