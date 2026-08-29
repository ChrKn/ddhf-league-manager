<?php

namespace App\Import;

/**
 * Where an import has got to.
 *
 * Only Applied means anything reached the tables a standing is computed from. Everything before
 * it lives entirely in the import's own tables, which is what makes abandoning one harmless.
 *
 * The path is not one-way: ImportRunner::withdraw() takes an applied import off those tables again
 * and returns it to Review, so a mis-import is corrected rather than repaired by hand.
 */
enum ImportStatus: string
{
    /** Files are up, but nobody has said which season and event they belong to. */
    case Draft = 'draft';

    /** Matched against the database; the rows are waiting for decisions. */
    case Review = 'review';

    /**
     * Written. The import is the record of what happened, and the only state from which it can be
     * taken back again - which puts it back to Review rather than ending it.
     */
    case Applied = 'applied';

    /** Put aside without writing. */
    case Discarded = 'discarded';

    public function label(): string
    {
        return match ($this) {
            self::Draft     => 'Hochgeladen',
            self::Review    => 'In Prüfung',
            self::Applied   => 'Übernommen',
            self::Discarded => 'Verworfen',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft     => 'gray',
            self::Review    => 'warning',
            self::Applied   => 'success',
            self::Discarded => 'danger',
        };
    }

    /** Whether the import may still be changed. */
    public function isOpen(): bool
    {
        return $this === self::Draft || $this === self::Review;
    }
}
