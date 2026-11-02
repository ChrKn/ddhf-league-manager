<?php

namespace App\Import;

/**
 * Everything one uploaded file holds: a tournament per filled worksheet, and what was left out.
 */
final class ReadFile
{
    /**
     * @param  list<ReadSheet>  $sheets
     * @param  list<string>  $notes  about the file as a whole, such as a skipped example sheet
     */
    public function __construct(
        public readonly array $sheets,
        public readonly array $notes = [],
    ) {
    }
}
