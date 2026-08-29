<?php

namespace App\Filament\Resources\Groups\Schemas;

use App\Data\Countries;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class GroupForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Toggle::make('is_active')
                    ->default(true)
                    ->label('Aktiv')
                    ->helperText('Aus, wenn der Verein sich aufgelöst hat oder nicht mehr ficht.'),
                Toggle::make('is_public')
                    ->default(true)
                    ->label('Öffentlich sichtbar')
                    ->helperText('Aus, wenn der Verein nicht genannt werden möchte. Ergebnisse, '
                        . 'Punkte und Ranglisten ändern sich dadurch nicht — der Name verschwindet '
                        . 'nur aus der öffentlichen Seite und der API, auch an den Fechtern.'),
                TextInput::make('name')
                    ->required(),
                TextInput::make('abbreviation')
                    ->label('Abkürzung'),
                Select::make('federations')
                    ->relationship('federations', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->label('Verbände')
                    ->helperText('In der Regel ein Dachverband, dazu beliebig viele Verbünde. '
                        . 'Mitgliedschaft im DDHF entscheidet, ob die Fechter dieses Vereins '
                        . 'in den Ranglisten stehen — ab dem nächsten Import, rückwirkend ändert '
                        . 'sich nichts.'),
                Select::make('country')
                    ->options(Countries::all())
                    ->default('DE')
                    ->label('Land (Staat)'),
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
