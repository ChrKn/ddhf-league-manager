<?php

namespace App\Import;

/**
 * Compares names that come from outside the database against the names we store.
 *
 * Source files are written by hand, so the same club shows up as "ASV Ossweil", "ASV Oßweil" or
 * "ASV Oßweil Historische Kampfkünste". This class folds both sides down far enough to compare
 * them, and scores how close they are.
 *
 * Not to be confused with App\Traits\NameNormalization: that one runs on write and keeps the
 * stored spelling consistent (e.V. becomes e. V., hyphens become en dashes). This one runs on
 * read and throws away exactly those differences again.
 */
final class NameMatcher
{
    /** Filler words that carry no identity. */
    private const NOISE = ['ev', 'verein', 'gesellschaft', 'abteilung', 'abt'];

    /** Clubs are named both ways, and no edit distance bridges "7" and "sieben". */
    private const NUMERALS = [
        '1' => 'eins', '2' => 'zwei', '3' => 'drei', '4' => 'vier', '5' => 'fuenf',
        '6' => 'sechs', '7' => 'sieben', '8' => 'acht', '9' => 'neun', '10' => 'zehn',
    ];

    private const TRANSLITERATION = [
        'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'å' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u',
        'ç' => 'c', 'ñ' => 'n', 'ý' => 'y',
    ];

    /**
     * Fold a name down to a comparable form: lower case, no diacritics, no punctuation,
     * no legal suffixes, numerals spelled out.
     */
    public static function normalize(string $name): string
    {
        $name = mb_strtolower(trim($name));
        $name = strtr($name, self::TRANSLITERATION);

        // Every kind of dash, dot and bracket becomes a separator. This also undoes the en dash
        // that NameNormalization writes into stored names.
        $name = preg_replace('/[^a-z0-9]+/u', ' ', $name);
        $name = trim(preg_replace('/\s+/', ' ', (string) $name));

        // "e. V." has already fallen apart into two tokens, so it has to go before tokenizing.
        $name = trim((string) preg_replace('/(^| )e v( |$)/', ' ', ' ' . $name . ' '));

        if ($name === '') {
            return '';
        }

        $tokens = [];

        foreach (explode(' ', $name) as $token) {
            $token = self::NUMERALS[$token] ?? $token;

            if ($token === '' || in_array($token, self::NOISE, true)) {
                continue;
            }

            $tokens[] = $token;
        }

        return implode(' ', $tokens);
    }

    /**
     * How close two names are, from 0.0 (nothing in common) to 1.0 (identical once normalized).
     *
     * Three measures are combined because they catch different kinds of sloppiness, and the
     * strongest one wins:
     *
     *  - edit distance catches typos ("Maximilan" for "Maximilian")
     *  - token overlap catches added or dropped words ("ASV Ossweil" inside the full club name)
     *  - containment catches a short name being a prefix of a long one ("Hammaborg")
     */
    public static function score(string $a, string $b): float
    {
        $a = self::normalize($a);
        $b = self::normalize($b);

        if ($a === '' || $b === '') {
            return 0.0;
        }

        if ($a === $b) {
            return 1.0;
        }

        return max(
            self::editDistanceScore($a, $b),
            self::tokenOverlapScore($a, $b),
            self::containmentScore($a, $b),
        );
    }

    /** True when the two names are the same once folded. */
    public static function matchesExactly(string $a, string $b): bool
    {
        $a = self::normalize($a);

        return $a !== '' && $a === self::normalize($b);
    }

    private static function editDistanceScore(string $a, string $b): float
    {
        $longest = max(strlen($a), strlen($b));

        if ($longest === 0) {
            return 0.0;
        }

        // levenshtein() works on bytes, which is fine here: normalize() already reduced both
        // sides to plain ASCII.
        return max(0.0, 1 - (levenshtein($a, $b) / $longest));
    }

    private static function tokenOverlapScore(string $a, string $b): float
    {
        $tokensA = explode(' ', $a);
        $tokensB = explode(' ', $b);
        $shared = count(array_intersect($tokensA, $tokensB));

        if ($shared === 0) {
            return 0.0;
        }

        // Measured against the longer side, so a single shared word out of five does not count
        // as a good match.
        return $shared / max(count($tokensA), count($tokensB));
    }

    private static function containmentScore(string $a, string $b): float
    {
        $shorter = strlen($a) <= strlen($b) ? $a : $b;
        $longer = $shorter === $a ? $b : $a;

        // Whole words only. Without the boundaries "ochs" would sit inside "hochschule".
        if (!preg_match('/(^| )' . preg_quote($shorter, '/') . '( |$)/', $longer)) {
            return 0.0;
        }

        // Deliberately short of 1.0: a contained name is strong evidence but never proof.
        return 0.95;
    }
}
