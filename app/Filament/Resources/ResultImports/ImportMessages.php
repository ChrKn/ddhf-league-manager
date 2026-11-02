<?php

namespace App\Filament\Resources\ResultImports;

use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;

/**
 * Lists of what the import found, as notifications that can be read rather than squinted at.
 *
 * A validation message under a field shows one line. What the import has to say is often ten -
 * every sheet of every file - so it goes into a notification that stays until it is dismissed,
 * one item per finding.
 */
final class ImportMessages
{
    /** @param list<string> $problems */
    public static function problems(string $title, array $problems): void
    {
        Notification::make()
            ->title($title)
            ->body(self::list($problems))
            ->danger()
            ->persistent()
            ->send();
    }

    /** @param list<string> $notes */
    public static function notes(string $title, array $notes): void
    {
        if ($notes === []) {
            return;
        }

        Notification::make()
            ->title($title)
            ->body(self::list($notes))
            ->warning()
            ->persistent()
            ->send();
    }

    /** @param list<string> $lines */
    public static function list(array $lines): HtmlString
    {
        $items = array_map(fn (string $line) => '<li>' . nl2br(e($line)) . '</li>', $lines);

        return new HtmlString('<ul style="list-style: disc; padding-left: 1.25rem">' . implode('', $items) . '</ul>');
    }
}
