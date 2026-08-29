<?php

namespace App\Filament\Resources\Fencers\Actions;

use App\Models\Fencer;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;

/**
 * Honours a request to be removed from the database.
 *
 * Fencers are never deleted, because their results would take the standings of past seasons
 * with them. Instead every personal attribute is cleared in one indivisible step. Doing this by
 * hand used to mean four separate edits, and a half finished one is exactly what makes a record
 * impossible to recognise later.
 *
 * What survives is the placement and the federation on each result, so the standing still counts
 * the right number of people in the right order and shows an unnamed row where the person was.
 * Removing somebody must not quietly promote everybody who finished behind them.
 */
class AnonymizeFencerAction
{
    public static function make(): Action
    {
        return Action::make('anonymize')
            ->label('Anonymisieren')
            ->icon(Heroicon::OutlinedUserMinus)
            ->color('danger')
            ->visible(fn (Fencer $record) => !$record->isAnonymized())
            ->requiresConfirmation()
            ->modalHeading('Fechter anonymisieren')
            ->modalDescription(
                'Alle personenbezogenen Angaben werden gelöscht: Titel, Name, Geburtsname, '
                . 'Geburtsdatum, Geschlecht, Nationalität und Vereinszugehörigkeit — auch die '
                . 'Vereinsangabe an den einzelnen Ergebnissen. Platzierung und Verband bleiben am '
                . 'Ergebnis stehen, damit der Fechter in den Ranglisten weiter an seiner Stelle '
                . 'auftaucht, dort aber nur noch als „' . Fencer::ANONYMOUS_NAME . '“. '
                . 'Das lässt sich nicht rückgängig machen.'
            )
            ->modalSubmitActionLabel('Endgültig anonymisieren')
            ->schema([
                Textarea::make('anonymization_reason')
                    ->label('Grund (optional)')
                    ->helperText(
                        'Nur zur internen Nachvollziehbarkeit und ohne Personenbezug, '
                        . 'z. B. „Löschwunsch per E-Mail vom 02.08.2026". Dieses Feld wird weder '
                        . 'über die API ausgegeben noch exportiert.'
                    )
                    ->maxLength(255)
                    ->rows(2),
            ])
            ->action(function (Fencer $record, array $data) {
                self::anonymize($record, $data['anonymization_reason'] ?? null);

                Notification::make()
                    ->title('Fechter anonymisiert')
                    ->body('Der Datensatz enthält keine personenbezogenen Angaben mehr.')
                    ->success()
                    ->send();
            });
    }

    /**
     * Kept separate from the action so it can be called and tested without Filament.
     */
    public static function anonymize(Fencer $fencer, ?string $reason = null): void
    {
        DB::transaction(function () use ($fencer, $reason) {
            // The name goes with everything else. Nothing is written in its place: what a reader
            // gets to see is derived from the flag by Fencer::display_name, so the record cannot
            // keep a placeholder that behaves like a name.
            $fencer->update([
                'title'                => null,
                'first_name'           => null,
                'last_name'            => null,
                'birth_name'           => null,
                'nationality'          => null,
                'date_of_birth'        => null,
                'gender'               => null,
                'group_id'             => null,
                'is_active'            => false,
                'anonymized_at'        => now(),
                'anonymization_reason' => $reason,
            ]);

            // Once every other attribute is gone, the club recorded with a result is the last
            // identifying trait left - in a small club, together with tournament and placement,
            // that is enough to work backwards to a person. Both spellings go: the resolved club
            // and the raw one the source file used.
            //
            // The placement and the federation stay. The placement is a fact about the
            // competition, and the federation is what still puts that placement in the standing
            // it was earned in, without saying whose it was.
            $fencer->results()->update([
                'group_id'          => null,
                'fencer_group_name' => null,
            ]);
        });
    }
}
