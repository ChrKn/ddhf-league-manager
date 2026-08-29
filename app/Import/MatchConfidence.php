<?php

namespace App\Import;

/**
 * How much trust a suggested match deserves.
 *
 * Only Exact and Alias are safe to apply without someone looking at them. Everything below
 * that is a suggestion, and the review file exists precisely so that a human decides.
 */
enum MatchConfidence: string
{
    /** The names are identical once folded. */
    case Exact = 'exact';

    /** A recorded alias points at this record. */
    case Alias = 'alias';

    /** Similar enough to be worth suggesting, but nobody has confirmed it. */
    case Likely = 'likely';

    /** The best candidate found, offered only so the reviewer sees what is nearby. */
    case Unsure = 'unsure';

    /** Nothing comparable in the database. */
    case Missing = 'missing';

    public function isCertain(): bool
    {
        return $this === self::Exact || $this === self::Alias;
    }

    public function label(): string
    {
        return match ($this) {
            self::Exact   => 'exakt',
            self::Alias   => 'Alias',
            self::Likely  => 'wahrscheinlich',
            self::Unsure  => 'unsicher',
            self::Missing => 'nicht gefunden',
        };
    }
}
