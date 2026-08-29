<?php

namespace Tests\Feature\Groups;

use App\Import\NameMatcher;
use App\Models\Federation;
use App\Models\Group;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What the name mutator may and may not do on the way into the database.
 *
 * It used to turn every hyphen into an en dash, which mangled ten club names - Neu-Ulm,
 * Grün-Weiß, ARMA-PL and the like - and could not be undone by hand, because correcting one and
 * saving it ran the mutator again. The space is the distinction: a dash standing alone between
 * spaces separates parts of a name, one glued between characters joins words.
 */
class NameNormalizationTest extends TestCase
{
    use RefreshDatabase;

    private function stored(string $name): string
    {
        return Group::create(['name' => $name, 'is_active' => true])->fresh()->name;
    }

    public function test_a_glued_hyphen_survives_being_saved(): void
    {
        // The case that was broken, and the reason it stayed broken: this is also what happens
        // when somebody corrects the name in the admin panel and presses save.
        $this->assertSame('Muster-Verein Neu-Ulm', $this->stored('Muster-Verein Neu-Ulm'));
        $this->assertSame('Grün-Weiß Musterstadt', $this->stored('Grün-Weiß Musterstadt'));
        $this->assertSame('ARMA-PL', $this->stored('ARMA-PL'));
        $this->assertSame('Chin-Woo-Schule', $this->stored('Chin-Woo-Schule'));
    }

    public function test_a_dash_between_spaces_still_becomes_an_en_dash(): void
    {
        $this->assertSame(
            'Musterschule – Historisches Fechten',
            $this->stored('Musterschule - Historisches Fechten'),
        );
    }

    public function test_both_kinds_in_one_name_are_told_apart(): void
    {
        $this->assertSame(
            'Musterhausen – Judo-Club Probstett',
            $this->stored('Musterhausen - Judo-Club Probstett'),
        );
    }

    public function test_the_legal_suffix_is_still_spaced_out(): void
    {
        $this->assertSame('Musterverein e. V.', $this->stored('Musterverein e.V.'));
        $this->assertSame('Musterverein e. V.', $this->stored('Musterverein e. V.'));
    }

    public function test_federations_are_normalised_the_same_way(): void
    {
        $federation = Federation::create(['name' => 'Musterbund Nord-Süd e.V.', 'is_active' => true]);

        $this->assertSame('Musterbund Nord-Süd e. V.', $federation->fresh()->name);
    }

    public function test_saving_a_name_twice_does_not_change_it_again(): void
    {
        // A mutator that is not idempotent slowly rewrites the database every time anybody
        // touches a record.
        $group = Group::create(['name' => 'Musterschule - Historisches Fechten e.V.', 'is_active' => true]);
        $once = $group->fresh()->name;

        $group->update(['name' => $once]);

        $this->assertSame($once, $group->fresh()->name);
    }

    public function test_which_dash_is_stored_makes_no_difference_to_the_matcher(): void
    {
        // Why the stored names could be corrected at all without touching the alias table, the
        // import or the club resolver: normalize turns every run of non-alphanumeric characters
        // into one space, so the two dashes have always been the same character to it.
        foreach ([
            ['Neu-Ulm', 'Neu–Ulm'],
            ['ARMA-PL', 'ARMA–PL'],
            ['Musterschule - Historisches Fechten', 'Musterschule – Historisches Fechten'],
        ] as [$hyphen, $dash]) {
            $this->assertSame(
                NameMatcher::normalize($hyphen),
                NameMatcher::normalize($dash),
                "\"{$hyphen}\" und \"{$dash}\" müssen gleich normalisieren",
            );
        }
    }
}
