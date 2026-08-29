<?php

namespace App\Import\Formats;

/**
 * One shape a result sheet can arrive in.
 *
 * Today there is exactly one: the DDHF submission template. The interface exists because there
 * will not always be - the tournament programmes in common use export their own shapes, and
 * copying those into the template by hand is the work this whole import is meant to remove.
 *
 * What every format has to deliver is the row shape below, because that is what the rest of the
 * import reads. A format that cannot say how large the field was, or how the tournament was
 * fenced, hands over an empty string and lets the review step ask.
 */
interface SheetFormat
{
    /** Stored with the uploaded file, so it can be read again the same way it was read first. */
    public static function key(): string;

    /** What the reviewer picks in the upload step. */
    public static function label(): string;

    /**
     * Whether this format is willing to read the file. Used to pick a format for an upload
     * nobody labelled, and to say "this is not that" before the rows are trusted.
     */
    public static function recognises(string $path): bool;

    /**
     * @return list<array{sheet: string, row: int, placement: string, name: string, club: string,
     *                    federation: string, region: string, participants: string, system: string}>
     *
     * @throws \RuntimeException when the file is not in this format after all
     */
    public static function read(string $path): array;
}
