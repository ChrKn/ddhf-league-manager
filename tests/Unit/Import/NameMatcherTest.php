<?php

namespace Tests\Unit\Import;

use App\Import\NameMatcher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The club cases are real: they come from comparing an imported result sheet against the database,
 * and the pairs that must not match are the ones a plain similarity check actually got wrong.
 * Club names may stand here as they are, because a club is a group and not a person.
 *
 * The fencer cases are invented for that reason, but not freely - see the note above
 * fencersThatMustNotMatch. Every name below is a placeholder built out of Muster, Probe and Prüf,
 * so that no row can be read as a statement about somebody.
 */
class NameMatcherTest extends TestCase
{
    /** The threshold above which the resolvers treat a club as found without asking. */
    private const CLUB_THRESHOLD = 0.75;

    /** The threshold above which a fencer may be suggested at all. */
    private const FENCER_THRESHOLD = 0.85;

    public function test_normalize_folds_case_diacritics_and_punctuation(): void
    {
        $this->assertSame('asv ossweil', NameMatcher::normalize('ASV Oßweil'));
        $this->assertSame('asv ossweil', NameMatcher::normalize('  ASV   Ossweil  '));
        $this->assertSame('europaeische schwertkunst', NameMatcher::normalize('Europäische Schwertkunst'));
    }

    public function test_normalize_drops_legal_suffixes(): void
    {
        $this->assertSame('leipziger klingen', NameMatcher::normalize('Leipziger Klingen e. V.'));
        $this->assertSame('leipziger klingen', NameMatcher::normalize('Leipziger Klingen e.V.'));
        $this->assertSame('osc berlin', NameMatcher::normalize('OSC Berlin e. V.'));
    }

    public function test_normalize_folds_the_en_dash_written_by_the_storage_mutator(): void
    {
        // App\Traits\NameNormalization turns "-" into "–" on write, so both have to fold alike.
        $this->assertSame(
            NameMatcher::normalize('Zwerch von Links - Historischer Schwertkampf e. V.'),
            NameMatcher::normalize('Zwerch von Links – Historischer Schwertkampf e. V.'),
        );
    }

    public function test_normalize_spells_out_numerals(): void
    {
        $this->assertSame('sieben schwerter', NameMatcher::normalize('7 Schwerter'));
        $this->assertSame('sieben schwerter', NameMatcher::normalize('Sieben Schwerter'));
    }

    public function test_normalize_handles_empty_input(): void
    {
        $this->assertSame('', NameMatcher::normalize(''));
        $this->assertSame('', NameMatcher::normalize('   '));
        $this->assertSame('', NameMatcher::normalize('e. V.'));
    }

    #[DataProvider('clubsThatMustMatch')]
    public function test_clubs_that_must_match(string $fromFile, string $fromDatabase): void
    {
        $score = NameMatcher::score($fromFile, $fromDatabase);

        $this->assertGreaterThanOrEqual(
            self::CLUB_THRESHOLD,
            $score,
            sprintf('"%s" should match "%s" but scored %.2f', $fromFile, $fromDatabase, $score),
        );
    }

    public static function clubsThatMustMatch(): array
    {
        return [
            'ss for sharp s plus dropped suffix' => ['ASV Ossweil', 'ASV Oßweil Historische Kampfkünste'],
            'short name inside long one' => ['Hammaborg', 'Hammaborg – Historischer Schwertkampf e. V.'],
            'legal suffix only' => ['OSC Berlin', 'OSC Berlin e. V.'],
            'case difference only' => ['Historisches Fechten im Raspo', 'Historisches Fechten im RASPO e. V.'],
            'numeral against word' => ['7 Schwerter', 'Sieben Schwerter'],
            'identical' => ['Schwabenfedern', 'Schwabenfedern'],
            'dash and suffix' => ['Zwerch von Links', 'Zwerch von Links – Historischer Schwertkampf e. V.'],
        ];
    }

    #[DataProvider('clubsThatMustNotMatch')]
    public function test_clubs_that_must_not_match(string $fromFile, string $fromDatabase): void
    {
        $score = NameMatcher::score($fromFile, $fromDatabase);

        $this->assertLessThan(
            self::CLUB_THRESHOLD,
            $score,
            sprintf('"%s" must not match "%s" but scored %.2f', $fromFile, $fromDatabase, $score),
        );
    }

    public static function clubsThatMustNotMatch(): array
    {
        return [
            // The best similarity hit for "ESK Augsburg" in the real database. Only an alias
            // can resolve this one.
            'abbreviation against unrelated club' => ['ESK Augsburg', 'INDES Regensburg'],
            'shared word only' => ['Schule des inneren Schwertes', 'Sieben Schwerter'],
            'different town, same sport' => ['Fechtzirkel Herrenberg', 'Historisches Fechten – Judo–Club Herrenberg e. V.'],
        ];
    }

    #[DataProvider('fencersThatMustMatch')]
    public function test_fencers_that_must_match(string $fromFile, string $fromDatabase): void
    {
        $score = NameMatcher::score($fromFile, $fromDatabase);

        $this->assertGreaterThanOrEqual(
            self::FENCER_THRESHOLD,
            $score,
            sprintf('"%s" should match "%s" but scored %.2f', $fromFile, $fromDatabase, $score),
        );
    }

    public static function fencersThatMustMatch(): array
    {
        return [
            'single dropped letter' => ['Erka Mustermann', 'Erika Mustermann'],       // 0.94
            'diacritic' => ['Bernd Probstoss', 'Bernd Probstoß'],                     // 1.00
            'identical' => ['Pia Probstetten', 'Pia Probstetten'],                    // 1.00
        ];
    }

    /**
     * The shapes a plain similarity check kept getting wrong. Every one of them is a different
     * person, and none may reach the threshold.
     *
     * The pairs are invented, but not freely: each one was measured to land on the same score as
     * the real false positive it stands for, because the point of the case is where it sits
     * relative to the threshold. A pair that scored 0.3 would pass this test while proving
     * nothing - which is why the surnames differ by a letter rather than by a whole word. Making
     * both halves of a pair read as placeholders is not enough; two unrelated placeholder names
     * score around 0.4 and would turn every case here into a free pass.
     *
     * The measured scores are noted per row, against a bar of 0.85.
     */
    #[DataProvider('fencersThatMustNotMatch')]
    public function test_fencers_that_must_not_match(string $fromFile, string $fromDatabase): void
    {
        $score = NameMatcher::score($fromFile, $fromDatabase);

        $this->assertLessThan(
            self::FENCER_THRESHOLD,
            $score,
            sprintf('"%s" must not match "%s" but scored %.2f', $fromFile, $fromDatabase, $score),
        );
    }

    public static function fencersThatMustNotMatch(): array
    {
        return [
            'first name a longer form of the other' => ['Frieda Musterloh', 'Friederike Pusterloh'], // 0.70
            'same surname only' => ['Max Mustermann', 'Erika Mustermann'],                          // 0.69
            'same surname, different person' => ['Max Probstett', 'Bernd Probstett'],               // 0.67
            'both names merely similar' => ['Otto Musterkuhl', 'Bernd Pusterkuhl'],                 // 0.625
            'shared first name' => ['Max Musterhain', 'Max Probenhain'],                            // 0.64
            'shared first name, short surname' => ['Max Probe', 'Max Prüf'],                        // 0.67
        ];
    }
}
