<?php

namespace App\Filament\Resources\Results\Schemas;

use App\Models\Fencer;
use App\Models\Tournament;
use App\Standings\Placement;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ResultForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('tournament_id')
                    ->relationship('tournament', 'id')
                    ->required()
                    ->getOptionLabelFromRecordUsing(fn(Tournament $record) => $record->display_name)
                    ->label('Turnier'),
                Select::make('fencer_id')
                    ->relationship('fencer', 'id')
                    ->required()
                    ->getOptionLabelFromRecordUsing(fn(Fencer $record) => $record->display_name_with_group)
                    ->label('Fechter'),
                TextInput::make('placement')
                    ->required()
                    ->label('Platz')
                    ->hint('Eine Platzierung, oder woran die Punkte hängen: "pools" für ein Ausscheiden
                        in der Vorrunde, "last-16" für ein Ausscheiden im Achtelfinale.')
                    ->rule(fn () => function (string $attribute, $value, Closure $fail) {
                        if (!Placement::describes((string) $value)) {
                            $fail('Erwartet wird eine Platzierung, "pools", oder eine Runde wie "last-16".');
                        }
                    })
                    // Stored the way the vocabulary spells it, so "4tel Finale" typed here ends up
                    // as last-8 like everything the importer writes.
                    ->dehydrateStateUsing(fn ($state) => Placement::describes((string) $state)
                        ? (string) Placement::fromSource((string) $state)
                        : $state),
                TextInput::make('fencer_group_name')
                    ->disabled()
                    ->placeholder('Automatisch generiert')
                    ->label('Gruppe'),
                // Die Kulanzregelung. Ausgefüllt heißt: zählt, obwohl der Verein am Turniertag
                // noch nicht Mitglied war. Ein Freitextfeld und kein Schalter, damit eine
                // Ausnahme nie ohne ihre Begründung dasteht.
                TextInput::make('counted_on_request')
                    ->label('Auf Antrag gewertet')
                    ->placeholder('Leer lassen — der Normalfall')
                    ->maxLength(250)
                    ->hint('Nur für die Kulanzregelung: das letzte Turnier vor dem Vereinsbeitritt
                        zählt auf Antrag. Hier gehört hin, wer wann zugestimmt hat.'),
            ]);
    }
}
