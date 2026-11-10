<?php

namespace Tests\Feature\Site;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The announcements on the public site: a list for the last twelve months, the newest three on the
 * front page, and nothing a reader is not meant to see - not the author, not raw HTML.
 */
class NewsTest extends TestCase
{
    use RefreshDatabase;

    private function announce(string $title, string $when, array $more = []): Announcement
    {
        $announcement = Announcement::create($more + ['title' => $title, 'body' => 'Text zu ' . $title]);
        $announcement->forceFill(['created_at' => $when])->save();

        return $announcement;
    }

    public function test_both_languages_have_the_page(): void
    {
        $this->get('/meldungen')->assertOk()->assertSee('In den letzten zwölf Monaten gab es keine Meldungen.');
        $this->get('/en/news')->assertOk()->assertSee('There were no notices in the last twelve months.');
    }

    public function test_it_shows_the_last_twelve_months_newest_first(): void
    {
        $this->announce('Ältere Korrektur', now()->subMonths(2)->toDateTimeString());
        $this->announce('Neue Korrektur', now()->subDay()->toDateTimeString());
        $this->announce('Vom vorletzten Jahr', now()->subMonths(13)->toDateTimeString());

        $this->get('/meldungen')
            ->assertOk()
            ->assertSeeInOrder(['Neue Korrektur', 'Ältere Korrektur'])
            ->assertDontSee('Vom vorletzten Jahr');
    }

    public function test_the_front_page_shows_the_newest_three(): void
    {
        foreach (range(1, 4) as $day) {
            $this->announce("Meldung Nummer {$day}", now()->subDays($day)->toDateTimeString());
        }

        $this->get('/')
            ->assertOk()
            ->assertSee('Aktuelle Meldungen')
            ->assertSeeInOrder(['Meldung Nummer 1', 'Meldung Nummer 2', 'Meldung Nummer 3'])
            ->assertDontSee('Meldung Nummer 4');
    }

    public function test_the_front_page_says_nothing_without_announcements(): void
    {
        $this->get('/')->assertOk()->assertDontSee('Aktuelle Meldungen');
    }

    public function test_english_shows_the_translation_or_the_german_text_marked_as_german(): void
    {
        $this->announce('Übersetzt', now()->subDay()->toDateTimeString(), [
            'title_en' => 'Translated', 'body_en' => 'English text',
        ]);
        $this->announce('Nur deutsch', now()->subDays(2)->toDateTimeString());

        $html = $this->get('/en/news')->assertOk()
            ->assertSee('Translated')
            ->assertSee('English text')
            ->assertDontSee('Übersetzt')
            ->assertSee('Nur deutsch')
            ->getContent();

        $this->assertMatchesRegularExpression('/<li\s+lang="de"\s*>\s*<details[^>]*>\s*<summary>.*?Nur deutsch/s', $html);
        $this->assertDoesNotMatchRegularExpression('/<li\s+lang="de"\s*>\s*<details[^>]*>\s*<summary>.*?Translated/s', $html);
    }

    public function test_the_update_date_is_shown_only_when_one_was_set(): void
    {
        $this->announce('Ohne Aktualisierung', now()->subDays(3)->toDateTimeString());

        $this->get('/meldungen')->assertDontSee('aktualisiert am');

        $this->announce('Mit Aktualisierung', now()->subDays(3)->toDateTimeString(), [
            'revised_at' => now()->subDay(),
        ]);

        $this->get('/meldungen')->assertSee('aktualisiert am ' . now()->subDay()->format('d.m.Y'));
    }

    public function test_the_time_is_shown_on_the_german_clock(): void
    {
        // 13:05 UTC in summer is 15:05 in Germany, 14:05 in winter.
        $this->travelTo(now()->setDate((int) now()->year, 7, 1)->setTime(12, 0));
        $this->announce('Sommerzeit', now()->setTime(13, 5)->toDateTimeString(), [
            'revised_at' => now()->setTime(13, 30),
        ]);

        $this->get('/meldungen')
            ->assertSee('01.07.' . now()->year . ', 15:05')
            ->assertSee('aktualisiert am 01.07.' . now()->year . ', 15:30')
            ->assertSee('datetime="' . now()->year . '-07-01T15:05:00+02:00"', false);

        $this->get('/en/news')->assertSee('1 Jul ' . now()->year . ', 15:05');
    }

    public function test_only_links_to_this_site_stay_in_the_same_tab(): void
    {
        $this->announce('Verlinkt', now()->toDateTimeString(), [
            'body' => '[hier](http://localhost/ranglisten), [relativ](/turniere) und [Verband](https://ddhf.de/)',
        ]);

        $html = $this->get('/meldungen')->assertOk()->getContent();

        $this->assertStringContainsString('<a href="http://localhost/ranglisten">hier</a>', $html);
        $this->assertStringContainsString('<a href="/turniere">relativ</a>', $html);
        $this->assertMatchesRegularExpression('/<a [^>]*target="_blank"[^>]*href="https:\/\/ddhf\.de\/">Verband<\/a>/', $html);
        $this->assertStringContainsString('--new-tab: " (öffnet in neuem Tab)"', $html);
    }

    public function test_markdown_is_rendered_and_html_is_not(): void
    {
        $this->announce('Formatiert', now()->toDateTimeString(), [
            'body' => "**fett** und [Link](https://ddhf.de/) <script>alert(1)</script> [böse](javascript:alert(1))",
        ]);

        $html = $this->get('/meldungen')->assertOk()->getContent();

        $this->assertStringContainsString('<strong>fett</strong>', $html);
        $this->assertStringContainsString('<a rel="noopener noreferrer" target="_blank" href="https://ddhf.de/">Link</a>', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function test_the_author_is_never_shown(): void
    {
        $author = User::create(['name' => 'Interne Verfasserin', 'email' => 'autor@example.test', 'password' => 'geheim']);
        $this->announce('Mit Autor', now()->toDateTimeString(), ['user_id' => $author->id]);

        $this->get('/meldungen')->assertOk()->assertSee('Mit Autor')->assertDontSee('Interne Verfasserin');
        $this->get('/')->assertOk()->assertDontSee('Interne Verfasserin');
    }

    public function test_there_is_no_json_for_it(): void
    {
        $this->get('/data/news.json')->assertNotFound();
        $this->get('/data/announcements.json')->assertNotFound();
    }
}
