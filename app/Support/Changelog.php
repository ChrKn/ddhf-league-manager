<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * CHANGELOG.md, read as the one place the running version is written down.
 *
 * The version is the topmost released heading - "## 1.6.0 - 2026-11-07" - and never "Unreleased".
 * Renaming that heading is part of making a release anyway, so the number shown in the footer is
 * the number in the file that describes the release, with nothing else to keep in step. Git is not
 * asked here: a server that was given the code as files has no repository to ask. deploy:check holds
 * the two against each other where there is one.
 *
 * English only, in every language of the site: it is written for whoever maintains the system,
 * and a translation would be a second document to keep current.
 */
final class Changelog
{
    /** "## 1.6.0 - 2026-11-07", tolerating "[1.6.0]" and a "v" in front. */
    private const RELEASE = '/^## \[?v?(\d+\.\d+\.\d+)\]?/m';

    private static ?string $file = null;

    public static function path(): string
    {
        return self::$file ?? base_path('CHANGELOG.md');
    }

    /** For tests: read another file in its place, or null to go back to the real one. */
    public static function usePath(?string $path): void
    {
        self::$file = $path;
    }

    /**
     * The version this code is, or null where the file is missing or names no release yet. Null
     * shows no version rather than a made-up one.
     */
    public static function version(): ?string
    {
        $markdown = self::markdown();

        return $markdown !== null && preg_match(self::RELEASE, $markdown, $match) === 1 ? $match[1] : null;
    }

    /**
     * The file as HTML, without its own top heading - the page sets that. Each release heading
     * gets the version as its id, so /changelog#1.6.0 lands on it.
     */
    public static function html(): ?string
    {
        $markdown = self::markdown();

        if ($markdown === null) {
            return null;
        }

        $markdown = preg_replace('/\A\s*# [^\n]*\n/', '', $markdown);

        // The file is ours, but there is no reason for it to be able to carry markup either.
        $html = Str::markdown($markdown, ['html_input' => 'escape', 'allow_unsafe_links' => false]);

        return preg_replace_callback(
            '/<h2>v?(\d+\.\d+\.\d+)/',
            fn (array $match) => '<h2 id="' . $match[1] . '">' . substr($match[0], 4),
            $html,
        );
    }

    private static function markdown(): ?string
    {
        $path = self::path();

        return is_file($path) ? (string) file_get_contents($path) : null;
    }
}
