<?php

namespace App\Support;

/**
 * The address of a file in public/, with its last change written into the query string.
 *
 * public/.htaccess lets browsers keep images for a year without asking again. That is only safe if
 * a changed file gets a new address - otherwise a new logo would reach a returning reader a year
 * late. There is no build step that could put a hash into the file name, so the modification time
 * does the same job: a deploy that changes the file changes the address, one that does not leaves
 * every cached copy valid.
 */
final class VersionedAsset
{
    public static function url(string $path): string
    {
        $file = public_path($path);

        // A missing file is still worth a link - the 404 then shows up where it belongs, in the
        // request for the file, and not as an exception that takes the whole page down.
        return is_file($file)
            ? asset($path) . '?v=' . filemtime($file)
            : asset($path);
    }
}
