<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Announcements\Pages\CreateAnnouncement;
use App\Filament\Resources\Announcements\Pages\EditAnnouncement;
use App\Filament\Resources\Announcements\Pages\ListAnnouncements;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Writing an announcement in the panel. The part worth testing is the public "updated on": it
 * moves when somebody ticks the box, and not because something was saved.
 */
class AnnouncementPanelTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name'     => 'Prüferin',
            'email'    => 'pruefung@example.test',
            'password' => 'geheim',
        ]);

        $this->actingAs($this->user);
    }

    public function test_the_list_opens(): void
    {
        Announcement::create(['title' => 'Eine Meldung', 'body' => 'Text']);

        Livewire::test(ListAnnouncements::class)->assertOk()->assertSee('Eine Meldung');
    }

    public function test_the_author_defaults_to_whoever_writes_it(): void
    {
        Livewire::test(CreateAnnouncement::class)
            ->assertSchemaStateSet(['user_id' => $this->user->id])
            ->fillForm(['title' => 'Ergebnis berichtigt', 'body' => 'Platz 3 war falsch.'])
            ->call('create')
            ->assertHasNoFormErrors();

        $announcement = Announcement::sole();

        $this->assertSame($this->user->id, $announcement->user_id);
        $this->assertNull($announcement->revised_at);
    }

    public function test_half_a_translation_is_refused(): void
    {
        Livewire::test(CreateAnnouncement::class)
            ->fillForm(['title' => 'Titel', 'body' => 'Text', 'title_en' => 'Title'])
            ->call('create')
            ->assertHasFormErrors(['body_en' => 'required_with']);

        $this->assertSame(0, Announcement::count());
    }

    public function test_saving_alone_does_not_mark_it_as_updated(): void
    {
        $announcement = Announcement::create(['title' => 'Titel', 'body' => 'Text']);

        Livewire::test(EditAnnouncement::class, ['record' => $announcement->getKey()])
            ->fillForm(['title' => 'Titel ohne Tippfehler'])
            ->call('save')
            ->assertHasNoFormErrors();

        $announcement->refresh();
        $this->assertSame('Titel ohne Tippfehler', $announcement->title);
        $this->assertNull($announcement->revised_at);
    }

    public function test_ticking_the_box_marks_it_as_updated_now(): void
    {
        $announcement = Announcement::create(['title' => 'Titel', 'body' => 'Text']);

        $this->travelTo(now()->addDays(5)->startOfMinute());

        Livewire::test(EditAnnouncement::class, ['record' => $announcement->getKey()])
            ->fillForm(['body' => 'Text, nachgetragen', 'mark_revised' => true])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertSchemaStateSet(['mark_revised' => false]);

        $this->assertTrue($announcement->refresh()->revised_at->equalTo(now()));
    }
}
