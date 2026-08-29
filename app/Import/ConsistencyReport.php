<?php

namespace App\Import;

/**
 * Whether a set of files may be planned at all.
 *
 * Problems stop the run: they are the disagreements that would score a whole tournament against
 * the wrong bracket, or put two files into one tournament. Notes are worth showing and nothing
 * more - a file announced as incomplete says so here.
 */
final class ConsistencyReport
{
    /**
     * @param  list<string>  $problems
     * @param  list<string>  $notes
     */
    public function __construct(
        public readonly array $problems = [],
        public readonly array $notes = [],
    ) {}

    public function agrees(): bool
    {
        return $this->problems === [];
    }
}
