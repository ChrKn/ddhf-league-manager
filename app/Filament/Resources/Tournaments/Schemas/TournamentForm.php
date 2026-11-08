<?php

namespace App\Filament\Resources\Tournaments\Schemas;

use App\Data\Regions;
use App\Models\Event;
use App\Models\Season;
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
                // Every point in the standing is computed against it, and the column has no null.
                // Left optional, an empty field went to the database as null and came back as a
                // server error instead of a sentence next to the field.
                TextInput::make('participant_count')
                    ->required()
                    ->integer()
                    ->minValue(1)
                    ->label('Teilnehmende'),
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
                Select::make('tournament_format_id')
                    ->relationship('tournamentFormat', 'name', fn ($query) => $query->ordered())
                    ->label('Turniersystem')
                    ->hint('Weitere Systeme unter „Turniersysteme“. Wertungsrelevant ist es nicht mehr -
                        das steht am einzelnen Ergebnis - aber ohne die Angabe liest sich eine Liste
                        aus Runden statt Platzierungen wie ein Erfassungsfehler.'),
                TextInput::make('public_id')
                    ->disabled()
                    ->placeholder('Automatisch generiert')
                    ->label('ID'),
            ]);
    }
}
