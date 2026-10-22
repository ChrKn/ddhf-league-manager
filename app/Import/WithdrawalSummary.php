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
 *
 * Anonymous starters are the exception, and they are removed. Nothing can be using one: the
 * resolver never suggests an anonymised record, so no later import can have attached a result to
 * it, and without a result there is nobody left in it. Left standing, each withdrawal would leave
 * another empty placeholder behind - which is how three of them came to be after Hanseschlag 2026.
 */
final class WithdrawalSummary
{
    /**
     * @param  list<string>  $tournaments  public ids of tournaments removed with their last result
     * @param  list<string>  $orphanedFencers  "Name (ID)", now without any result
     * @param  list<string>  $orphanedClubs  "Name (ID)", now without any result
     * @param  int  $removedPlaceholders  anonymous starters deleted because nothing was left in them
     */
    public function __construct(
        public readonly int $results = 0,
        public readonly array $tournaments = [],
        public readonly array $orphanedFencers = [],
        public readonly array $orphanedClubs = [],
        public readonly bool $dryRun = false,
        public readonly int $removedPlaceholders = 0,
    ) {}

    public function orphans(): int
    {
        return count($this->orphanedFencers) + count($this->orphanedClubs);
    }
}
