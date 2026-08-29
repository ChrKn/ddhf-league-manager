<?php

namespace App\Import\Formats;

use App\Import\ResultSheetReader;

/**
 * The DDHF submission template - the shape organisers are asked to send today.
 *
 * The reading itself stays in ResultSheetReader, which knows the template down to the example
 * block. This only presents it as one format among the ones that may follow.
 */
class DdhfTemplate implements SheetFormat
{
    public static function key(): string
    {
        return 'ddhf-vorlage';
    }

    public static function label(): string
    {
        return 'DDHF-Vorlage';
    }

    public static function recognises(string $path): bool
    {
        try {
            static::read($path);
        } catch (\RuntimeException) {
            return false;
        }

        return true;
    }

    public static function read(string $path): array
    {
        return ResultSheetReader::read($path);
    }
}
