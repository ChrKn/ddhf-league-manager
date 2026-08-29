<?php

namespace Tests\Feature\Groups;

use App\Federations\FederationKind;
use App\Models\Federation;
use App\Models\Group;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A club in more than one federation.
 *
 * The case that forced this: INDES Kulmbach is in the DDHF and in INDES, INDES Salzburg is in the
 * ÖFHF and in INDES. One column could hold one of the two and there was no honest way to choose.
 *
 * The thing to hold onto is that membership does not travel. Both clubs are in INDES; only one of
 * them is ours, because each states its own memberships and nothing is inherited through the
 * network they share. A club that shares nothing but a name - INDES Halle is not an INDES member -
 * is the same point from the other side, which is why the name is never what decides.
 */
class FederationMembershipTest extends TestCase
{
    use RefreshDatabase;

    private Federation $ddhf;

    private Federation $oefhf;

    private Federation $indes;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ddhf = Federation::create([
            'name'         => 'Deutscher Dachverband für historisches Fechten e. V.',
            'abbreviation' => Federation::OWN,
            'country'      => 'DE',
            'is_active'    => true,
        ]);

        $this->oefhf = Federation::create([
            'name'         => 'Österreichischer Fachverband für historisches Fechten',
            'abbreviation' => 'ÖFHF',
            'country'      => 'AT',
            'is_active'    => true,
        ]);

        // No country: a network does not have one, which is half of what makes it a different
        // sort of thing from the two above.
        $this->indes = Federation::create([
            'name'      => 'INDES',
            'kind'      => FederationKind::Association,
            'is_active' => true,
        ]);
    }

    private function club(string $name, Federation ...$federations): Group
    {
        $club = Group::create(['name' => $name, 'is_active' => true]);
        $club->federations()->attach($federations);

        return $club;
    }

    public function test_a_club_holds_more_than_one_membership(): void
    {
        $kulmbach = $this->club('INDES Kulmbach', $this->ddhf, $this->indes);

        $this->assertCount(2, $kulmbach->federations);
        $this->assertTrue($kulmbach->federations->contains($this->ddhf));
        $this->assertTrue($kulmbach->federations->contains($this->indes));
    }

    public function test_the_two_kinds_are_asked_for_separately(): void
    {
        $kulmbach = $this->club('INDES Kulmbach', $this->ddhf, $this->indes);

        $this->assertSame(['Deutscher Dachverband für historisches Fechten e. V.'],
            $kulmbach->nationalFederations->pluck('name')->all());
        $this->assertSame(['INDES'], $kulmbach->associations->pluck('name')->all());
    }

    public function test_membership_does_not_travel_through_the_network(): void
    {
        $kulmbach = $this->club('INDES Kulmbach', $this->ddhf, $this->indes);
        $salzburg = $this->club('INDES Salzburg', $this->oefhf, $this->indes);

        // Both are in INDES. Only one is ours, and it is the one that says so itself. Passing
        // membership down through INDES would put Salzburg in a DDHF standing.
        $this->assertSame($this->ddhf->id, $kulmbach->scoringFederation()?->id);
        $this->assertSame($this->oefhf->id, $salzburg->scoringFederation()?->id);
    }

    public function test_a_shared_name_is_not_a_membership(): void
    {
        // INDES Halle is called that and is not one of them. Nothing in the model reads names.
        $halle = $this->club('INDES – Historische Fechtkuenste Halle a.d. Saale e. V.', $this->ddhf);

        $this->assertCount(0, $halle->associations);
        $this->assertSame($this->ddhf->id, $halle->scoringFederation()?->id);
    }

    public function test_ours_wins_however_the_memberships_were_entered(): void
    {
        // The order rows go in must not decide who is ranked, so it is asked both ways round.
        $first = $this->club('Erster', $this->ddhf, $this->indes);
        $second = $this->club('Zweiter', $this->indes, $this->ddhf);

        $this->assertSame($this->ddhf->id, $first->scoringFederation()?->id);
        $this->assertSame($this->ddhf->id, $second->scoringFederation()?->id);
    }

    public function test_a_network_alone_scores_for_nobody(): void
    {
        $club = $this->club('Nur im Verbund', $this->indes);

        // Belonging to INDES says nothing about who ranks you, so there is nothing to record on
        // a result - and a result with no federation counts towards no standing.
        $this->assertNull($club->scoringFederation());
    }

    public function test_a_club_in_nothing_has_no_federation_to_record(): void
    {
        $this->assertNull($this->club('Ohne alles')->scoringFederation());
    }

    public function test_the_same_membership_cannot_be_stated_twice(): void
    {
        $club = $this->club('Einmal reicht', $this->ddhf);

        $club->federations()->syncWithoutDetaching([$this->ddhf->id]);

        $this->assertCount(1, $club->fresh()->federations);
    }

    public function test_dropping_a_club_takes_its_memberships_with_it(): void
    {
        $club = $this->club('Aufgelöst', $this->ddhf, $this->indes);

        $club->delete();

        $this->assertSame(0, $this->ddhf->groups()->count());
        $this->assertSame(0, $this->indes->groups()->count());
    }

    public function test_a_federation_lists_its_clubs_from_both_directions(): void
    {
        $this->club('INDES Kulmbach', $this->ddhf, $this->indes);
        $this->club('INDES Salzburg', $this->oefhf, $this->indes);
        $this->club('Nur DDHF', $this->ddhf);

        // The report that would have forced this change: a federation's clubs, completely.
        $this->assertSame(2, $this->ddhf->groups()->count());
        $this->assertSame(2, $this->indes->groups()->count());
        $this->assertSame(1, $this->oefhf->groups()->count());
    }

    public function test_a_federation_is_a_governing_body_unless_it_says_otherwise(): void
    {
        // Everything on record when this was built was a national federation, so that is what a
        // row is until somebody says it is a network.
        $this->assertTrue(Federation::create(['name' => 'Ohne Angabe'])->isNational());
        $this->assertFalse($this->indes->isNational());
    }
}
