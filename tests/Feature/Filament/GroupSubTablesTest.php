<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Groups\Pages\EditGroup;
use App\Filament\Resources\Groups\RelationManagers\AliasesRelationManager;
use App\Filament\Resources\Groups\RelationManagers\LocationsRelationManager;
use App\Models\Group;
use App\Models\GroupAlias;
use App\Models\GroupLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The two lists hanging off a club: the towns it trains in, and the spellings it is imported under.
 *
 * Both are the club's own subordinate data - nothing points at either, so neither is covered by the
 * guard that stops a record being deleted out from under something. A wrong entry in them is a
 * correction, and both should behave the same way about it. One of them did not: a location could
 * be deleted in bulk but not on its own, which meant ticking a box and going through a menu to
 * undo one typo, while the aliases beside it offered the direct way.
 */
class GroupSubTablesTest extends TestCase
{
    use RefreshDatabase;

    private Group $club;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsMaintainer();

        $this->club = Group::create(['name' => 'Schildwache Potsdam', 'is_active' => true]);
    }

    private function locations(): \Livewire\Features\SupportTesting\Testable
    {
        return Livewire::test(LocationsRelationManager::class, [
            'ownerRecord' => $this->club,
            'pageClass'   => EditGroup::class,
        ]);
    }

    public function test_a_single_location_can_be_taken_back(): void
    {
        $location = GroupLocation::create([
            'group_id' => $this->club->id,
            'locality' => 'Vertippt',
            'region'   => 'Brandenburg',
            'country'  => 'DE',
        ]);

        $this->locations()->callTableAction('delete', $location);

        $this->assertNull($location->fresh());
    }

    public function test_deleting_one_leaves_the_others_alone(): void
    {
        foreach (['Potsdam', 'Vertippt'] as $town) {
            GroupLocation::create([
                'group_id' => $this->club->id,
                'locality' => $town,
                'region'   => 'Brandenburg',
                'country'  => 'DE',
            ]);
        }

        $wrong = GroupLocation::where('locality', 'Vertippt')->sole();

        $this->locations()->callTableAction('delete', $wrong);

        $this->assertSame(['Potsdam'], GroupLocation::pluck('locality')->all());
        $this->assertNotNull($this->club->fresh(), 'Der Verein selbst bleibt.');
    }

    public function test_both_lists_behave_the_same_way(): void
    {
        GroupLocation::create([
            'group_id' => $this->club->id,
            'locality' => 'Potsdam',
            'region'   => 'Brandenburg',
            'country'  => 'DE',
        ]);

        GroupAlias::create(['group_id' => $this->club->id, 'alias' => 'Schildwache']);

        $aliases = Livewire::test(AliasesRelationManager::class, [
            'ownerRecord' => $this->club,
            'pageClass'   => EditGroup::class,
        ]);

        // The point of the fix, asked as a comparison rather than as two separate facts: whichever
        // of the two lists somebody is looking at, the same handles are there.
        foreach ([$this->locations(), $aliases] as $list) {
            $list->assertTableActionExists('edit')->assertTableActionExists('delete');
        }
    }

    public function test_a_spelling_another_club_already_uses_is_refused(): void
    {
        $other = Group::create(['name' => 'Twerchhau Berlin', 'is_active' => true]);
        GroupAlias::create(['group_id' => $other->id, 'alias' => 'Twerchhau e.V. Berlin']);

        // Not the same string, so the unique index lets it pass; the importer folds both to
        // "twerchhau berlin" and would then resolve only one of them.
        Livewire::test(AliasesRelationManager::class, [
            'ownerRecord' => $this->club,
            'pageClass'   => EditGroup::class,
        ])
            ->callTableAction('create', data: ['alias' => 'Twerchhau Berlin e. V.'])
            ->assertHasTableActionErrors(['alias']);

        $this->assertSame(1, GroupAlias::count());
    }

    public function test_a_spelling_that_is_another_clubs_name_is_refused(): void
    {
        Group::create(['name' => 'Twerchhau Berlin', 'is_active' => true]);

        // A club name beats an alias when the importer looks one up, so this row would be stored
        // and never read.
        Livewire::test(AliasesRelationManager::class, [
            'ownerRecord' => $this->club,
            'pageClass'   => EditGroup::class,
        ])
            ->callTableAction('create', data: ['alias' => 'Twerchhau Berlin e.V.'])
            ->assertHasTableActionErrors(['alias']);

        $this->assertSame(0, GroupAlias::count());
    }

    public function test_a_second_spelling_for_this_club_is_still_accepted(): void
    {
        GroupAlias::create(['group_id' => $this->club->id, 'alias' => 'Schildwache']);

        // The live table has nineteen pairs like this. They all name one club and resolve fine.
        Livewire::test(AliasesRelationManager::class, [
            'ownerRecord' => $this->club,
            'pageClass'   => EditGroup::class,
        ])
            ->callTableAction('create', data: ['alias' => 'Schildwache e. V.'])
            ->assertHasNoTableActionErrors();

        $this->assertSame(2, GroupAlias::count());
    }
}
