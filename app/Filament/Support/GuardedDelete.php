<?php

namespace App\Filament\Support;

use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

/**
 * A delete that refuses once anything depends on the record, and says what.
 *
 * The panel used to offer an unguarded delete on everything. Two different things went wrong
 * behind that one button.
 *
 * The database refuses the load-bearing ones itself - a fencer with results, a tournament with
 * results, a season with tournaments are all RESTRICT - but it refuses them as a raw SQL error,
 * which tells whoever clicked nothing they can act on.
 *
 * The dangerous ones are the references that are SET NULL, because those succeed. Deleting a
 * federation empties `results.federation_id` for every result ever fenced for it, and
 * StandingCalculator scores nobody whose result does not name our own federation - so deleting one
 * row silently empties every standing. Deleting a club strips the club off historical results.
 * Deleting a ruleset drops results out of a ruleset-mode standing. No error, no warning, and
 * nobody notices until the tables move.
 *
 * So the rule is one sentence: **a record may be deleted while nothing points at it.** That still
 * clears up an event created by accident or a duplicate ruleset, which is what a delete is
 * legitimately for, and it makes a hole impossible - a hole being, by definition, something that
 * was pointed at.
 */
class GuardedDelete
{
    /**
     * Takes no arguments on purpose: what protects a record is looked up from its own class, so
     * every call site is the same line and none of them can be configured wrongly. The map lives
     * in App\Filament\Support\Dependents.
     */
    public static function make(): DeleteAction
    {
        return DeleteAction::make()
            ->modalDescription(fn (Model $record): string => static::found($record) === []
                ? 'Auf diesen Datensatz verweist nichts. Löschen ist in Ordnung.'
                : 'Darauf verweist noch etwas. Das Löschen wird abgelehnt.')
            ->before(function (Model $record, DeleteAction $action): void {
                $found = static::found($record);

                if ($found === []) {
                    return;
                }

                Notification::make()
                    ->title('Wird nicht gelöscht')
                    ->body('Darauf verweist noch: ' . implode(', ', $found) . '. '
                        . 'Solange das so ist, wäre das Löschen ein Loch und keine Korrektur — '
                        . 'zum Ausblenden gibt es den Schalter „Aktiv".')
                    ->danger()
                    ->persistent()
                    ->send();

                $action->halt();
            });
    }

    /** @return list<string> */
    private static function found(Model $record): array
    {
        $found = [];

        foreach (Dependents::for($record) as $label => $count) {
            $n = $count($record);

            if ($n > 0) {
                $found[] = "{$n} {$label}";
            }
        }

        return $found;
    }
}
