<?php

namespace App\Import;

/**
 * What a run of the planner produced: the review rows, and how good the matches were.
 *
 * The rows are the same shape the review csv has always had, because two front ends now read
 * them - the console writes them out as a file, the panel stores them as records.
 *
 * Alongside each row sits what it was matched to. The csv cannot carry a model and does not need
 * to; the panel wants the record itself rather than looking the public id back up, which would
 * be a query per row for something the planner had in its hand a moment earlier.
 */
final class ImportPlan
{
    /**
     * @param  list<array<string, string>>  $rows
     * @param  array{fechter: array<string, int>, verein: array<string, int>}  $counts
     * @param  list<array{sheet: string, row: int, fencer: Suggestion, group: Suggestion}>  $matches
     */
    public function __construct(
        public readonly array $rows,
        public readonly array $counts,
        public readonly array $matches = [],
    ) {}

    /** How many rows still wait for somebody to decide. */
    public function open(): int
    {
        return count(array_filter($this->rows, fn (array $row) => $row['aktion'] === ''));
    }
}
