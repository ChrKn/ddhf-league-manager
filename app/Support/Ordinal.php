<?php

namespace App\Support;

/**
 * A rank written the way the reader's language writes one.
 *
 * German puts a full stop behind the number and is done; English appends a suffix that depends on
 * the last two digits. This is number formatting and not vocabulary - unlike "Vorrunde" or
 * "4tel Finale", nobody has to decide what "2nd" is called. A German full stop in an English
 * sentence is simply wrong, so it does not wait for the federation's word list.
 *
 * Written out rather than taken from NumberFormatter because that needs ext-intl, and the host
 * this will run on is not chosen yet. Two languages with rules this short are not worth a
 * dependency that a shared-hosting package might not carry.
 */
final class Ordinal
{
    /** Anything that is not English is written the German way. There are two languages. */
    public static function format(int $number, string $locale = 'de'): string
    {
        return $locale === 'en'
            ? $number . self::suffix($number)
            : "{$number}.";
    }

    private static function suffix(int $number): string
    {
        // Eleventh to thirteenth break the rule the last digit would give them, and so does every
        // hundred-and-eleventh after them.
        if (in_array(abs($number) % 100, [11, 12, 13], true)) {
            return 'th';
        }

        return match (abs($number) % 10) {
            1       => 'st',
            2       => 'nd',
            3       => 'rd',
            default => 'th',
        };
    }
}
