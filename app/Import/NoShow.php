<?php

namespace App\Import;

/**
 * A row in a result file for somebody who registered and never fenced.
 *
 * Some organisers send the registration list with the results filled in, and leave the ones who
 * did not turn up in it with a word where the placement would be. Those rows are not results: no
 * placement, no record, and above all no part of the field size. Counted, three cancellations
 * would lift a field of 22 into the column for 25, and a longer registration list would be a way
 * to earn more points for the same tournament.
 *
 * So they are recognised here, left out of the rows the field size is checked against, and planned
 * as skipped. How many there were is said, as a number for the registrations on the tournament -
 * a proposal for a person to take or leave, never written by the import. No reason is kept: "krank"
 * is a statement about somebody's health, and the record has no use for it.
 *
 * Somebody who fenced and then gave up - "Aufgabe", "Verletzung" - is not this. They are part of
 * the field and keep the place they went out on; that row is left to the reviewer as before.
 */
final class NoShow
{
    /** Normalised: lower case, runs of spaces, dots and hyphens as one space. */
    private const WORDS = [
        'nicht angetreten',
        'nicht erschienen',
        'nicht gestartet',
        'abgemeldet',
        'abgesagt',
        'n a',
        'na',
        'dns',
        'did not start',
        'no show',
        'noshow',
    ];

    /** Whether this placement cell says the person never fenced. */
    public static function marks(string $placement): bool
    {
        $normalized = trim((string) preg_replace('/[\s\-.\/]+/u', ' ', mb_strtolower(trim($placement))));

        return in_array($normalized, self::WORDS, true);
    }

    /**
     * How many of these rows say so.
     *
     * @param  iterable<array<string, mixed>>  $rows
     */
    public static function count(iterable $rows): int
    {
        $count = 0;

        foreach ($rows as $row) {
            if (self::marks((string) ($row['placement'] ?? ''))) {
                $count++;
            }
        }

        return $count;
    }
}
