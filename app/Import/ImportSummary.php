<?php

namespace App\Import;

/**
 * What a write actually did, in the words a reviewer needs to check it.
 *
 * The two skip counts are kept apart on purpose. A row somebody set aside is a decision; a row
 * whose result was already there is the sign of a second run over the same file, and that is
 * worth seeing rather than counting as ordinary.
 */
final class ImportSummary
{
    /**
     * @param  list<string>  $clubs
     * @param  list<string>  $fencers
     * @param  list<string>  $aliases
     * @param  list<string>  $aliasConflicts  spellings that were not remembered because another
     *                                        club already holds them. Nothing went wrong with the
     *                                        results; the next import of this spelling will still
     *                                        land on the other club, and that is worth reading.
     * @param  array<int, int>  $resultIds  index of the row handed in => the result it became.
     *                                      What a stored import writes back, so a row can say
     *                                      later what came of it.
     */
    public function __construct(
        public readonly int $results = 0,
        public readonly array $clubs = [],
        public readonly array $fencers = [],
        public readonly array $aliases = [],
        public readonly int $skipped = 0,
        public readonly int $duplicates = 0,
        public readonly bool $dryRun = false,
        public readonly array $resultIds = [],
        public readonly array $aliasConflicts = [],
    ) {}
}
