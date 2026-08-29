<?php

namespace App\Filament\Resources\Tournaments\Schemas;

use App\Data\Regions;
use App\Models\Event;
use App\Models\Season;
use App\Models\Tournament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TournamentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Name')
                    ->hint('Optional. Wird benötigt, wenn mehrere Turniere auf einer Veranstaltung stattfinden,
                        die sonst nicht unterscheidbar wären. Z. B. Langes Schwert Anfänger und Langes Schwert
                        Fortgeschrittene.'),
                Select::make('event_id')
                    ->relationship('event', 'name')
                    ->getOptionLabelFromRecordUsing(fn(Event $record) => $record->display_name)
                    ->required()
                    ->label('Veranstaltung'),
                Select::make('season_id')
                    ->relationship('season', 'id')
                    ->getOptionLabelFromRecordUsing(fn(Season $record) => $record->display_name)
                    ->required()
                    ->label('Saison'),
                TextInput::make('participant_count')
                    ->numeric()
                    ->label('Teilnehmer'),
                DatePicker::make('start_date')
                    ->label('Gefochten am')
                    ->hint('Optional. Leer lassen, wenn das Datum der Veranstaltung gilt.')
                    ->helperText('Nur ausfüllen, wenn dieses Turnier an einem anderen Tag stattfand als '
                        . 'der Beginn der Veranstaltung — etwa Säbel am Samstag und Langschwert am Sonntag.'),
                DatePicker::make('end_date')
                    ->label('Gefochten bis')
                    ->afterOrEqual('start_date')
                    ->hint('Optional. Nur bei Turnieren über mehrere Tage.')
                    ->helperText('Etwa Vorrunde am ersten und Endrunde am zweiten Tag. '
                        . 'Bleibt das Feld leer, gilt der Tag oben.'),
                Select::make('ruleset_id')
                    ->relationship('ruleset', 'name')
                    ->label('Regelwerk'),
                Select::make('region')
                    ->options(Regions::all())
                    ->label('Region'),
                Select::make('format')
                    ->options(array_combine(Tournament::FORMATS, Tournament::FORMATS))
                    ->label('Turniersystem')
                    ->hint('Wie der Ausrichter es angegeben hat. Wertungsrelevant ist es nicht mehr -
                        das steht am einzelnen Ergebnis - aber ohne die Angabe liest sich eine Liste
                        aus Runden statt Platzierungen wie ein Erfassungsfehler.'),
                TextInput::make('public_id')
                    ->disabled()
                    ->placeholder('Automatisch generiert')
                    ->label('ID'),
            ]);
    }
}
