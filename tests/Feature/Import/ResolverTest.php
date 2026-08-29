<?php

namespace Tests\Feature\Import;

use App\Filament\Resources\Fencers\Actions\AnonymizeFencerAction;
use App\Import\FencerResolver;
use App\Import\GroupResolver;
use App\Import\MatchConfidence;
use App\Models\Fencer;
use App\Models\Group;
use App\Models\GroupAlias;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResolverTest extends TestCase
{
    use RefreshDatabase;

    private function group(string $name): Group
    {
        return Group::create(['name' => $name, 'is_active' => true]);
    }

    private function fencer(string $first, string $last, ?Group $group = null): Fencer
    {
        return Fencer::create([
            'first_name' => $first,
            'last_name'  => $last,
            'group_id'   => $group?->id,
            'is_active'  => true,
        ]);
    }

    public function test_a_sheet_from_before_a_marriage_finds_the_same_person(): void
    {
        // fencers.birth_name is there for exactly this, and the resolver used to ignore it.
        $marie = $this->fencer('Marie', 'Musterfrau');
        $marie->update(['birth_name' => 'Probstein']);

        $match = (new FencerResolver())->resolve('Marie Probstein', null);

        $this->assertTrue($match->record?->is($marie));
        // Offered, not taken: a maiden name is a weaker statement than the current one, and two
        // sisters share it.
        $this->assertSame(MatchConfidence::Likely, $match->confidence);
        $this->assertFalse($match->confidence->isCertain());
    }

    public function test_the_current_name_still_wins_over_a_birth_name(): void
    {
        $married = $this->fencer('Peter', 'Musterfrau');
        $married->update(['birth_name' => 'Musterlein']);
        $namesake = $this->fencer('Peter', 'Musterlein');

        $match = (new FencerResolver())->resolve('Peter Musterlein', null);

        $this->assertTrue($match->record?->is($namesake));
        $this->assertSame(MatchConfidence::Exact, $match->confidence);
    }

    public function test_identical_club_name_is_exact(): void
    {
        $this->group('Schwabenfedern');

        $match = (new GroupResolver())->resolve('Schwabenfedern');

        $this->assertSame(MatchConfidence::Exact, $match->confidence);
        $this->assertTrue($match->confidence->isCertain());
    }

    public function test_legal_suffix_does_not_prevent_an_exact_match(): void
    {
        $this->group('OSC Berlin e. V.');

        $this->assertSame(MatchConfidence::Exact, (new GroupResolver())->resolve('OSC Berlin')->confidence);
    }

    public function test_an_alias_resolves_what_similarity_cannot(): void
    {
        $esk = $this->group('Europäische Schwertkunst');
        $this->group('INDES Regensburg');
        GroupAlias::create(['group_id' => $esk->id, 'alias' => 'ESK Augsburg']);

        $match = (new GroupResolver())->resolve('ESK Augsburg');

        $this->assertSame(MatchConfidence::Alias, $match->confidence);
        $this->assertSame($esk->id, $match->record->id);
    }

    public function test_without_the_alias_the_same_name_stays_unresolved(): void
    {
        $this->group('Europäische Schwertkunst');
        $this->group('INDES Regensburg');

        // The point of the alias table: this must never silently become INDES Regensburg.
        $match = (new GroupResolver())->resolve('ESK Augsburg');

        $this->assertFalse($match->confidence->isCertain());
        $this->assertNotSame(MatchConfidence::Likely, $match->confidence);
    }

    public function test_a_club_not_marked_as_a_member_is_reported_missing_instead_of_guessed(): void
    {
        $this->group('Historisches Fechten – Judo–Club Herrenberg e. V.');

        // The source file leaves the federation column empty for this one, so it is not the
        // DDHF club from the same town.
        $match = (new GroupResolver())->resolve('Fechtzirkel Herrenberg', expectedInDatabase: false);

        $this->assertSame(MatchConfidence::Missing, $match->confidence);
        $this->assertFalse($match->found());
    }

    public function test_remembering_an_alias_makes_the_next_lookup_certain(): void
    {
        $esk = $this->group('Europäische Schwertkunst');
        $resolver = new GroupResolver();

        $resolver->rememberAlias('ESK Starnberg', $esk);

        $this->assertSame(MatchConfidence::Alias, $resolver->resolve('ESK Starnberg')->confidence);
    }

    public function test_an_alias_equal_to_the_club_name_is_not_stored(): void
    {
        $group = $this->group('Schwabenfedern');

        (new GroupResolver())->rememberAlias('schwabenfedern', $group);

        $this->assertSame(0, GroupAlias::count());
    }

    public function test_a_typo_inside_the_known_club_is_matched(): void
    {
        $esk = $this->group('Europäische Schwertkunst');
        $this->fencer('Peter', 'Probstett', $esk);

        // Measured at 87 %, against the 80 % a name needs inside a known club.
        $match = (new FencerResolver())->resolve('Petre Probstett', $esk);

        $this->assertSame(MatchConfidence::Likely, $match->confidence);
        $this->assertSame('Peter Probstett', $match->record->display_name);
    }

    public function test_a_different_person_in_another_club_is_not_matched(): void
    {
        $osc = $this->group('OSC Berlin e. V.');
        $motu = $this->group('IN MOTU');
        $this->fencer('Bernd', 'Musterlein', $motu);

        // Measured at 71 % by plain similarity, and wrong. It may be shown, never confirmed.
        $match = (new FencerResolver())->resolve('Bernd Musterholdt', $osc);

        $this->assertFalse($match->confidence->isCertain());
        $this->assertNotSame(MatchConfidence::Likely, $match->confidence);
    }

    public function test_the_same_surname_in_the_same_club_is_not_enough(): void
    {
        $osc = $this->group('OSC Berlin e. V.');
        $this->fencer('Marie', 'Muster', $osc);

        $match = (new FencerResolver())->resolve('Nikolai Muster', $osc);

        $this->assertFalse($match->confidence->isCertain());
        $this->assertNotSame(MatchConfidence::Likely, $match->confidence);
    }

    public function test_an_exact_name_wins_regardless_of_club(): void
    {
        $a = $this->group('Schwabenfedern');
        $this->fencer('Ida', 'Musterloh', $a);

        $match = (new FencerResolver())->resolve('Ida Musterloh', null);

        $this->assertSame(MatchConfidence::Exact, $match->confidence);
    }

    public function test_an_anonymized_fencer_is_never_suggested(): void
    {
        $group = $this->group('Schwabenfedern');
        $anonymous = $this->fencer('Erika', 'Mustermann', $group);
        AnonymizeFencerAction::anonymize($anonymous);

        // Every further anonymous row carries the same placeholder name. Matching it would be an
        // exact hit, so it would be applied without anyone looking at it, and unrelated results
        // from different events would end up on one person.
        $match = (new FencerResolver())->resolve(Fencer::ANONYMOUS_NAME, $group);

        $this->assertSame(MatchConfidence::Missing, $match->confidence);
        $this->assertFalse($match->found());
    }

    public function test_a_placeholder_created_by_the_import_is_never_suggested_either(): void
    {
        // create_inactive disables the record but sets no anonymisation date, so checking the
        // flag alone lets the placeholder back in. It did: the three anonymous starters of 2024
        // all resolved onto one record that had stood for someone else since 2023.
        $group = $this->group('Schwabenfedern');

        Fencer::create([
            'is_active'  => false,
            'first_name' => '',
            'last_name'  => Fencer::ANONYMOUS_NAME,
            'group_id'   => $group->id,
        ]);

        $match = (new FencerResolver())->resolve(Fencer::ANONYMOUS_NAME, $group);

        $this->assertSame(MatchConfidence::Missing, $match->confidence);
        $this->assertFalse($match->found());
    }

    public function test_a_placeholder_remembered_during_a_run_is_never_suggested_either(): void
    {
        $resolver = new FencerResolver();
        $group = $this->group('Schwabenfedern');

        $placeholder = Fencer::create([
            'is_active'  => false,
            'first_name' => '',
            'last_name'  => Fencer::ANONYMOUS_NAME,
            'group_id'   => $group->id,
        ]);

        $resolver->remember($placeholder);

        $this->assertFalse($resolver->resolve(Fencer::ANONYMOUS_NAME, $group)->found());
        $this->assertSame($placeholder->id, $resolver->byPublicId($placeholder->public_id)?->id);
    }

    public function test_an_anonymized_fencer_is_still_reachable_by_id(): void
    {
        $anonymous = $this->fencer('Erika', 'Mustermann', $this->group('Schwabenfedern'));
        AnonymizeFencerAction::anonymize($anonymous);

        // A review file may point at them deliberately, which has to keep working.
        $this->assertSame(
            $anonymous->id,
            (new FencerResolver())->byPublicId($anonymous->public_id)?->id,
        );
    }

    public function test_split_name_puts_everything_before_the_last_word_into_the_first_name(): void
    {
        $this->assertSame(['Violetta', 'Vorlage-Muster'], FencerResolver::splitName('Violetta Vorlage-Muster'));
        $this->assertSame(['Hans Peter', 'Musterlein'], FencerResolver::splitName('Hans Peter Musterlein'));
        $this->assertSame(['', 'Vio'], FencerResolver::splitName('Vio'));
    }
}
