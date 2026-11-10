<?php

namespace App\Filament\Resources\Seasons\Schemas;

use App\Models\Standing;
use App\Standings\ScoringMode;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SeasonForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('standing_id')
                    ->relationship('standing', 'id')
                    ->getOptionLabelFromRecordUsing(fn(Standing $record) => $record->display_name)
                    ->required()
                    ->label('Rangliste'),
                Select::make('scoring_matrix_id')
                    ->relationship('scoring_matrix', 'name')
                    ->required()
                    ->label('Punkteschlüssel'),
                Select::make('scoring_mode')
                    ->options(ScoringMode::options())
                    ->default(ScoringMode::Standard->value)
                    ->required()
                    ->helperText('Zonen- und Regelwerksystem verwerfen Turniere ohne Zone bzw. ohne Regelwerk.')
                    ->label('Auswertungssystem'),
                // The season's own threshold, kept apart from the matrix: the official table is one
                // table, and "gilt nur für nicht offen Langschwert" is a rule about the standing.
                TextInput::make('min_participants')
                    ->integer()
                    ->minValue(1)
                    ->nullable()
                    ->label('Gewertet ab Teilnehmenden')
                    ->helperText('Leer = Schwelle ergibt sich aus der Matrix. Ausgefüllt wird ein '
                        . 'Turnier mit weniger Angetretenen nicht gewertet, auch wenn der '
                        . 'Punkteschlüssel eine Spalte dafür hat — etwa 25 für Langschwert offen.'),
                TextInput::make('year')
                    ->required()
                    ->label('Jahr'),
                Toggle::make('is_public')
                    ->default(true)
                    ->label('Öffentlich sichtbar')
                    ->helperText('Aus, solange die Saison noch nicht freigegeben ist. Sie erscheint '
                        . 'dann weder auf der öffentlichen Seite noch in der API oder der Suche, '
                        . 'auch nicht über ihre Turniere. Ergebnisse und Punkte bleiben unverändert.'),
                Toggle::make('division_choice_required')
                    ->label('Kategorie muss gewählt werden')
                    ->helperText('Wer in dieser Saison in beiden Kategorien derselben Waffe gefochten '
                        . 'hat, wird nur dort gewertet, wo die Person unter „Gewertete Fechtende" eingetragen '
                        . 'ist — und ohne Eintrag in keiner von beiden. Bis 2024 stand dieselbe Person '
                        . 'in beiden Ranglisten, dort bleibt der Schalter aus.'),
                TextInput::make('public_id')
                    ->disabled()
                    ->placeholder('Automatisch generiert')
                    ->label('ID'),
            ]);
    }
}
