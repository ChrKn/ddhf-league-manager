<?php

namespace App\Import\Formats;

use RuntimeException;

/**
 * The formats an upload may be in.
 *
 * Adding one means writing a SheetFormat and naming it here; nothing else in the import has to
 * know. Order matters for recognition: the first format willing to read a file gets it, so the
 * strictest belongs first.
 */
class SheetFormats
{
    /** @var list<class-string<SheetFormat>> */
    public const ALL = [
        DdhfTemplate::class,
    ];

    /** @return array<string, string> key => label, for a form field */
    public static function options(): array
    {
        $options = [];

        foreach (self::ALL as $format) {
            $options[$format::key()] = $format::label();
        }

        return $options;
    }

    /** @return class-string<SheetFormat> */
    public static function byKey(string $key): string
    {
        foreach (self::ALL as $format) {
            if ($format::key() === $key) {
                return $format;
            }
        }

        throw new RuntimeException("Unbekanntes Dateiformat: {$key}");
    }

    /**
     * The first format willing to read this file, or null when none is.
     *
     * @return class-string<SheetFormat>|null
     */
    public static function recognise(string $path): ?string
    {
        foreach (self::ALL as $format) {
            if ($format::recognises($path)) {
                return $format;
            }
        }

        return null;
    }
}
