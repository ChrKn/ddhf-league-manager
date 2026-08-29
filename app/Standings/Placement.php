<?php

namespace App\Standings;

use App\Support\Ordinal;
use InvalidArgumentException;
use Stringable;

/**
 * What a fencer's result is, expressed the way the rules score it.
 *
 * The official points table is one grid with three readings of the same columns - its header says
 * so: "Platzierung / Runde / Finale". The column headed "7-8" is also "8er Runde" and also
 * "4tel Finale", and the last column, "bzw. Pools", is worth one point whatever the field size.
 * A result therefore lands in exactly one column, but it can arrive there in one of three ways:
 *
 *     "17"       an all-against-all, where the placement is the rank someone finished on
 *     "last-16"  a bracket, where sixteen were still in when this one went out
 *     "pools"    a bracket with a preliminary, which this one did not survive
 *
 * Places one to four stay numbers even in a bracket. The table gives them no round name, and it
 * could not: third and fourth both go out in the round of four, and they score differently.
 *
 * The submitted sheets already write it this way - the Berlin HEMA Cup 2025 file puts "4tel Finale"
 * and "Pools" straight into its placement column - so fromSource() takes the organisers' wording
 * and from() takes ours.
 */
final class Placement implements Stringable
{
    /** Knocked out in the preliminary pools. The last column of the table. */
    public const POOLS = 'pools';

    /**
     * The round sizes the table has a column for. Anything between them is rounded up by the
     * evaluator: whoever went out with eighteen left had not reached the round of sixteen.
     */
    public const ROUNDS = [6, 8, 12, 16, 24, 32, 48, 64];

    /**
     * Below this a round is not a round but a placement. The final and the third place match
     * produce places one to four, and the table scores them as places.
     */
    private const SMALLEST_ROUND = 5;

    /** The names the table itself uses. Only powers of two have a "Finale" name. */
    private const LABELS = [
        8  => '4tel Finale',
        16 => '8tel Finale',
        32 => '16tel Finale',
        64 => '32tel Finale',
    ];

    /** What organisers write, normalised, mapped to what we store. */
    private const SYNONYMS = [
        'pool'          => self::POOLS,
        'pools'         => self::POOLS,
        'poolphase'     => self::POOLS,
        'vorrunde'      => self::POOLS,
        'gruppenphase'  => self::POOLS,
        'pool stage'    => self::POOLS,
        'group stage'   => self::POOLS,
        '4tel finale'   => 'last-8',
        'viertelfinale' => 'last-8',
        'quarterfinal'  => 'last-8',
        'quarterfinals' => 'last-8',
        'quarter final' => 'last-8',
        'top 8'         => 'last-8',
        '8tel finale'   => 'last-16',
        'achtelfinale'  => 'last-16',
        'round of 16'   => 'last-16',
        'top 16'        => 'last-16',
        '16tel finale'  => 'last-32',
        'round of 32'   => 'last-32',
        'top 32'        => 'last-32',
        '32tel finale'  => 'last-64',
        'round of 64'   => 'last-64',
        'top 64'        => 'last-64',
    ];

    private function __construct(
        public readonly string $value,
        public readonly ?int $rank,
        public readonly ?int $round_size,
    ) {}

    /**
     * @throws InvalidArgumentException When the value is none of the three shapes.
     */
    public static function from(string $value): self
    {
        $value = trim($value);

        if ($value === self::POOLS) {
            return new self(self::POOLS, null, null);
        }

        if (preg_match('/^last-(\d+)$/', $value, $matches) === 1) {
            $size = (int) $matches[1];

            if ($size < self::SMALLEST_ROUND) {
                throw new InvalidArgumentException(
                    "\"{$value}\" nennt eine Runde von {$size} Verbliebenen. Die letzten vier werden als "
                    . 'Platzierung festgehalten (1 bis 4), weil die Punktetabelle ihnen keine Rundenspalte gibt.'
                );
            }

            return new self($value, null, $size);
        }

        if (preg_match('/^\d+$/', $value) === 1 && (int) $value > 0) {
            return new self($value, (int) $value, null);
        }

        throw new InvalidArgumentException(
            "\"{$value}\" ist keine Platzierung. Erlaubt sind: eine Zahl ab 1, " . self::POOLS
            . ', oder eine Runde wie ' . implode(', ', array_map(
                fn (int $size) => "last-{$size}",
                array_slice(self::ROUNDS, 0, 4)
            )) . ' …'
        );
    }

    /**
     * The same, but forgiving of what the organisers write. Their sheets say "4tel Finale",
     * "Top 16" or "Pools", and rewriting that by hand before every import is how transcription
     * errors get in.
     *
     * @throws InvalidArgumentException When the wording is not one we know.
     */
    public static function fromSource(string $value): self
    {
        $value = trim($value);

        // A spreadsheet formats a rank as "1." often enough to be worth taking.
        if (preg_match('/^(\d+)\.?$/', $value, $matches) === 1) {
            return self::from($matches[1]);
        }

        $normalized = self::normalize($value);

        if (isset(self::SYNONYMS[$normalized])) {
            return self::from(self::SYNONYMS[$normalized]);
        }

        // "6er Runde", "48er Runde" - the columns the table names by size rather than by a final.
        if (preg_match('/^(\d+)er runde$/', $normalized, $matches) === 1) {
            return self::from('last-' . $matches[1]);
        }

        // No wording we know, so it has to be one of ours already. Checked against the original
        // rather than the normalised form, which turned "last-16" into "last 16" - and that is
        // not a value anybody stores.
        return self::from($value);
    }

    /** Whether the value can be read as a source placement without throwing. */
    public static function describes(string $value): bool
    {
        try {
            self::fromSource($value);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    public function isRank(): bool
    {
        return $this->rank !== null;
    }

    public function isRound(): bool
    {
        return $this->round_size !== null;
    }

    public function isPools(): bool
    {
        return $this->value === self::POOLS;
    }

    /**
     * Orders a result list the way the table reads, left to right. A rank sorts by itself, a round
     * by how many were still in it, and the pool exits come last - they are the only ones whose
     * order among themselves says nothing.
     */
    public function sortKey(): int
    {
        return match (true) {
            $this->isPools() => PHP_INT_MAX,
            $this->isRound() => $this->round_size,
            default          => $this->rank,
        };
    }

    /**
     * What a person should read.
     *
     * The round names stay German in every language: they are the points table's own headings, and
     * what they are called in English is the federation's to say rather than ours to invent - the
     * same decision already recorded for the disciplines and the divisions. A rank is different.
     * "2." is not a word anybody has to agree on, it is how German writes an ordinal, and in an
     * English sentence it is wrong rather than untranslated.
     *
     * The language is passed in and defaults to German, so that the API, which hands this out as
     * placement_name, keeps saying what it has always said. Only the English pages ask for English.
     */
    public function label(string $locale = 'de'): string
    {
        return match (true) {
            $this->isPools() => 'Vorrunde',
            $this->isRound() => self::LABELS[$this->round_size] ?? "{$this->round_size}er Runde",
            default          => Ordinal::format($this->rank, $locale),
        };
    }

    public function __toString(): string
    {
        return $this->value;
    }

    /** Lower case, and every run of spaces, hyphens or dots reduced to one space. */
    private static function normalize(string $value): string
    {
        return trim((string) preg_replace('/[\s\-.]+/u', ' ', mb_strtolower(trim($value))));
    }
}
