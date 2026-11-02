<?php

namespace App\Import;

/**
 * One tournament as a result file delivers it: a worksheet, or the whole file where there is one.
 *
 * Problems and notes are kept apart on purpose. A problem means the sheet cannot be taken in as it
 * is - the header is not the template's, there are no results - and only a corrected file helps.
 * A note is something the person mapping the files can settle on the screen, such as a field size
 * that is missing or stated twice: it is said, and then the import goes on.
 */
final class ReadSheet
{
    /**
     * @param  string|null  $worksheet  null where the file holds only this one tournament
     * @param  list<array<string, string|int>>  $rows  the shape SheetFormat::read() describes
     * @param  int|null  $participants  what the field size column says, as far as it can be read
     * @param  list<string>  $problems
     * @param  list<string>  $notes
     */
    public function __construct(
        public readonly ?string $worksheet,
        public readonly array $rows,
        public readonly ?int $participants,
        public readonly array $problems = [],
        public readonly array $notes = [],
    ) {
    }

    public function readable(): bool
    {
        return $this->problems === [];
    }

    /**
     * The name a person recognises the tournament by: the file, and where the file holds several,
     * the sheet as well.
     */
    public function label(string $file): string
    {
        return $this->worksheet === null ? $file : "{$file} · {$this->worksheet}";
    }
}
