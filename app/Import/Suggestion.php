<?php

namespace App\Import;

use Illuminate\Database\Eloquent\Model;

/**
 * One suggested match: what was looked for, what was found, and how much that is worth.
 */
final class Suggestion
{
    public function __construct(
        public readonly string $searched,
        public readonly ?Model $record,
        public readonly MatchConfidence $confidence,
        public readonly float $score = 0.0,
    ) {}

    public static function missing(string $searched): self
    {
        return new self($searched, null, MatchConfidence::Missing);
    }

    public function found(): bool
    {
        return $this->record !== null;
    }

    /** The public_id of the match, or an empty string when nothing was found. */
    public function publicId(): string
    {
        return (string) ($this->record?->public_id ?? '');
    }

    /** A readable name for the match, for the review file. */
    public function name(): string
    {
        if ($this->record === null) {
            return '';
        }

        return (string) ($this->record->display_name ?? $this->record->name ?? '');
    }
}
