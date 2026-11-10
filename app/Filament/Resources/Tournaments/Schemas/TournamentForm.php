<?php

namespace App\Filament\Resources\Tournaments\Schemas;

use App\Data\Regions;
use App\Models\Event;
use App\Models\Season;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
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
                    ->label('Teilnehmende (angetreten)')
                    ->helperText('Wer mindestens ein Gefecht ausgetragen hat. Wer vor dem ersten Gefecht '
                        . 'abgesagt hat, zählt nicht. Nach dieser Zahl richten sich die Punkte.'),
                // Evidence, and nothing else: no point is ever read at this number. See
                // SeasonScoring::pointsFor().
                TextInput::make('registered_count')
                    ->live(onBlur: true)
                    ->integer()
                    ->minValue(0)
                    ->nullable()
                    ->gte('participant_count')
                    ->label('Gemeldet')
                    ->helperText('Optional. Zahl der Meldungen zum Meldeschluss. Fließt nie in die Punkte '
                        . 'ein, wird öffentlich als „25 gemeldet · 22 angetreten“ angezeigt. Keine Namen '
                        . 'und keine Gründe für Absagen eintragen.'),
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

                // Shaped after the Kulanzregelung on a result: grounds instead of a switch, so a
                // decision never stands there without saying why it was made.
                Section::make('Wertung auf Beschluss')
                    ->description('Nur ausfüllen, wenn der Verband beschlossen hat, dieses Turnier zu werten, '
                        . 'obwohl weniger Fechtende angetreten sind, als die Saison verlangt. Gewertet wird '
                        . 'dann nach dem Punkteschlüssel der Saison, in der Spalte der Angetretenen — nie in '
                        . 'der der Gemeldeten. Wo der Punkteschlüssel selbst nichts vergibt, ändert auch ein '
                        . 'Beschluss nichts.')
                    ->schema([
                        Textarea::make('scored_on_decision')
                            ->live(onBlur: true)
                            ->label('Begründung')
                            ->placeholder('Leer lassen — der Normalfall')
                            ->rows(3)
                            ->helperText('Wird öffentlich am Turnier angezeigt. Wer hat was beschlossen, '
                                . 'auf welcher Grundlage. Keine Namen oder Gründe einzelner Absagen.'),
                        DatePicker::make('decided_on')
                            ->label('Beschlossen am')
                            ->requiredWith('scored_on_decision'),
                        Select::make('announcement_id')
                            ->relationship('announcement', 'title', fn ($query) => $query->orderByDesc('created_at'))
                            ->searchable()
                            ->preload()
                            ->label('Meldung')
                            ->helperText('Optional. Die öffentliche Meldung zum Beschluss, auf die das Turnier '
                                . 'verlinkt, solange sie unter „Meldungen“ steht.'),
                        // A warning, not an error: the decision may well stand without it, but the
                        // number it rests on is then nowhere to be seen.
                        Callout::make('Der Beleg fehlt')
                            ->description('Es ist eine Begründung eingetragen, aber keine Zahl unter „Gemeldet“. '
                                . 'Ohne sie lässt sich öffentlich nicht nachvollziehen, wie viele gemeldet waren.')
                            ->warning()
                            ->visible(fn (Get $get) => filled($get('scored_on_decision')) && blank($get('registered_count'))),
                    ])
                    ->collapsible()
                    ->columnSpanFull(),
            ]);
    }
}
