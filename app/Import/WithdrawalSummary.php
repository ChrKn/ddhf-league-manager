<?php

namespace App\Import;

/**
 * What taking an import back actually did.
 *
 * The two lists at the end are the interesting part. Fencers and clubs are not removed - a later
 * import may be using them by now - so what is worth saying is which of them are left without a
 * single result. That is the question somebody can act on, and it is also the honest one: who
 * created a club cannot be established after the fact, because ImportWriter::group() uses
 * firstOrCreate and a hit on an existing name looks exactly like a new one afterwards.
 */
final class WithdrawalSummary
{
    /**
     * @param  list<string>  $tournaments  public ids of tournaments removed with their last result
     * @param  list<string>  $orphanedFencers  "Name (ID)", now without any result
     * @param  list<string>  $orphanedClubs  "Name (ID)", now without any result
     */
    public function __construct(
        public readonly int $results = 0,
        public readonly array $tournaments = [],
        public readonly array $orphanedFencers = [],
        public readonly array $orphanedClubs = [],
        public readonly bool $dryRun = false,
    ) {}

    public function orphans(): int
    {
        return count($this->orphanedFencers) + count($this->orphanedClubs);
    }
}
