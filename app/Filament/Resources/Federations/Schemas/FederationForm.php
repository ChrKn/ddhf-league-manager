<?php

namespace App\Filament\Resources\Federations\Schemas;

use App\Data\Countries;
use App\Federations\FederationKind;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class FederationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Toggle::make('is_active')
                    ->default(true)
                    ->label('Aktiv')
                    ->helperText('Aus, wenn der Verband nicht mehr besteht.'),
                Toggle::make('is_public')
                    ->default(true)
                    ->label('Öffentlich sichtbar')
                    ->helperText('Aus, wenn der Verband nicht genannt werden möchte. Auf die '
                        . 'Wertung hat das keinen Einfluss: wer Punkte bekommt, entscheidet sich '
                        . 'weiterhin am Verband des Ergebnisses.'),
                TextInput::make('name')
                    ->required(),
                Select::make('kind')
                    ->options(FederationKind::options())
                    ->default(FederationKind::National->value)
                    ->required()
                    ->selectablePlaceholder(false)
                    ->label('Art')
                    ->helperText(fn ($state): string => ($state instanceof FederationKind
                        ? $state
                        : FederationKind::tryFrom((string) $state))?->description() ?? '')
                    ->live(),
                TextInput::make('english_name')
                    ->label('Englischer Name')
                    ->helperText('Nur nötig, wenn der Name sonst nicht zu lesen ist — nicht der Vollständigkeit halber übersetzen.'),
                TextInput::make('abbreviation')
                    ->label('Abkürzung'),
                Select::make('country')
                    ->options(Countries::all())
                    ->label('Land (Staat)')
                    ->helperText('Ein Land kann mehrere Verbände haben, etwa nach Waffengattung.'),
                TextInput::make('website_url')
                    ->url()
                    ->label('Website URL'),
                TextInput::make('logo_url')
                    ->url()
                    ->label('Logo URL'),
                TextInput::make('public_id')
                    ->disabled()
                    ->placeholder('Automatisch generiert')
                    ->label('ID'),
            ]);
    }
}
