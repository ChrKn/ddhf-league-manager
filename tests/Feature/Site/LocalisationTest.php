<?php

namespace Tests\Feature\Site;

use App\Models\Discipline;
use App\Models\Division;
use App\Models\Event;
use App\Models\Federation;
use App\Models\Fencer;
use App\Models\Group;
use App\Models\Result;
use App\Models\ScoringMatrix;
use App\Models\Season;
use App\Models\Standing;
use App\Models\Tournament;
use App\Standings\ScoringMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * German at the root, English under /en.
 *
 * The promise worth testing is not that words were translated - it is that a reader who arrives
 * in one language stays in it. Every link on a page has to lead to the same language, with the
 * single exception of the switch, and the switch has to lead to the page being read rather than
 * to the other language's front page.
 */
class LocalisationTest extends TestCase
{
    use RefreshDatabase;

    private Season $season;

    private Tournament $tournament;

    protected function setUp(): void
    {
        parent::setUp();

        $ddhf = Federation::create([
            'name'         => 'Deutscher Dachverband für historisches Fechten e. V.',
            'abbreviation' => Federation::OWN,
            'is_active'    => true,
        ]);

        $club = Group::create([
            'name'          => 'Ochs Historische Kampfkünste',
            'is_active'     => true,
        ]);

        $club->federations()->attach($ddhf);

        $this->season = Season::create([
            'year'              => (int) now()->year,
            'standing_id'       => Standing::create([
                'discipline_id' => Discipline::create(['name' => 'Langes Schwert'])->id,
                'division_id'   => Division::create(['name' => 'offen'])->id,
            ])->id,
            'scoring_matrix_id' => ScoringMatrix::create([
                'name'   => 'Punkteschlüssel Test',
                'matrix' => json_encode([[
                    'participants' => ['min' => 0],
                    'points'       => [['place' => ['min' => 1, 'points' => 10]]],
                ]]),
            ])->id,
            'scoring_mode' => ScoringMode::Standard,
        ]);

        $this->tournament = Tournament::create([
            'season_id'         => $this->season->id,
            'event_id'          => Event::create([
                'name'       => 'Musterturnier',
                'start_date' => now()->startOfYear()->addMonths(3)->toDateString(),
            ])->id,
            'participant_count' => 2,
            'format'            => 'Turnierbaum',
        ]);

        Result::create([
            'tournament_id' => $this->tournament->id,
            'fencer_id'     => Fencer::create([
                'first_name' => 'Muster',
                'last_name'  => 'Fechter A',
                'is_active'  => true,
                'group_id'   => $club->id,
            ])->id,
            'group_id'      => $club->id,
            'federation_id' => $ddhf->id,
            'placement'     => '1',
        ]);
    }

    /** @return array<string, string> German path => English path */
    private function pages(): array
    {
        return [
            '/'                => '/en',
            '/ranglisten'      => '/en/standings',
            '/ddhf-turniere'   => '/en/ddhf-tournaments',
            '/ranglisten/' . $this->season->public_id        => '/en/standings/' . $this->season->public_id,
            '/ddhf-turniere/' . $this->tournament->public_id => '/en/ddhf-tournaments/' . $this->tournament->public_id,
        ];
    }

    public function test_each_language_addresses_a_page_in_its_own_words(): void
    {
        $this->get('/ranglisten')->assertOk();
        $this->get('/en/standings')->assertOk();

        // And only in its own words: a German path under /en is not a second address for the
        // English page, it is nothing at all.
        $this->get('/en/ranglisten')->assertNotFound();
        $this->get('/en/ddhf-turniere')->assertNotFound();
        $this->get('/standings')->assertNotFound();
        $this->get('/ddhf-tournaments')->assertNotFound();
    }

    public function test_every_page_answers_in_both_languages(): void
    {
        foreach ($this->pages() as $german => $english) {
            $this->get($german)->assertOk();
            $this->get($english)->assertOk();
        }
    }

    public function test_the_page_declares_the_language_it_is_written_in(): void
    {
        $this->get('/')->assertOk()->assertSee('<html lang="de">', false);
        $this->get('/en')->assertOk()->assertSee('<html lang="en">', false);
    }

    public function test_the_chrome_is_translated_but_the_data_is_not(): void
    {
        $english = $this->get('/en/standings')->assertOk();

        $english->assertSee('Standings')
            ->assertSee('Discipline')
            ->assertSee('Division')
            ->assertDontSee('Disziplin')
            ->assertDontSee('Abteilung');

        // Discipline and division come out of the database and stay as they are stored until the
        // federation says what they are called in English.
        $english->assertSee('Langes Schwert')->assertSee('offen');

        $this->get('/ranglisten')->assertOk()->assertSee('Disziplin')->assertDontSee('Discipline');
    }

    public function test_a_reader_stays_in_the_language_they_arrived_in(): void
    {
        foreach ($this->pages() as $german => $english) {
            foreach ([$german => 'de', $english => 'en'] as $path => $locale) {
                preg_match_all(
                    '#href="http://localhost(/[^"]*)?"#',
                    $this->get($path)->assertOk()->getContent(),
                    $found,
                );

                $links = array_values(array_unique(array_filter(
                    $found[1],
                    fn (string $href) => !str_starts_with($href, '/img'),
                )));

                $toEnglish = array_filter($links, fn ($href) => $href === '/en' || str_starts_with($href, '/en/'));
                $leaving = $locale === 'de' ? count($toEnglish) : count($links) - count($toEnglish);

                // Exactly one: the switch. Everything else stays put.
                $this->assertSame(1, $leaving, "Auf {$path} führen {$leaving} Links aus der Sprache heraus");
            }
        }
    }

    public function test_the_switch_leads_to_the_same_page_in_the_other_language(): void
    {
        foreach ($this->pages() as $german => $english) {
            $this->assertSame($english, $this->switchOn($german), "Umschalter auf {$german}");
            $this->assertSame($german, $this->switchOn($english), "Umschalter auf {$english}");
        }
    }

    public function test_no_german_wording_is_left_hard_coded_in_an_english_page(): void
    {
        // Against the German language file rather than a list of words I happened to think of.
        // The list is what let a hard-coded German sentence sit in the English footer: the
        // heading above it was translated, so a check for "Mitgliedschaft" passed while the
        // sentence underneath stayed German.
        $german = collect(\Illuminate\Support\Arr::dot(trans('site', [], 'de')))
            ->filter(fn ($value, $key) => is_string($value) && $key !== 'date_format')
            // Split at the placeholders rather than stripping them: a sentence with a link in
            // the middle never appears in the markup as one piece, so comparing the whole
            // string would quietly match nothing. The pieces around the link do appear.
            ->map(fn (string $value) => array_values(array_filter(
                array_map('trim', preg_split('/:\w+/', $value)),
                fn (string $piece) => mb_strlen($piece) > 12,
            )))
            ->filter(fn (array $pieces) => $pieces !== []);

        foreach (array_values($this->pages()) as $path) {
            $html = $this->get($path)->assertOk()->getContent();

            foreach ($german as $key => $pieces) {
                if (trans("site.{$key}", [], 'en') === trans("site.{$key}", [], 'de')) {
                    continue;   // deliberately the same in both, such as a proper name
                }

                foreach ($pieces as $piece) {
                    $this->assertStringNotContainsString(
                        $piece,
                        $html,
                        "Der deutsche Text zu site.{$key} steht auf {$path}: \"{$piece}\"",
                    );
                }
            }
        }
    }

    public function test_the_membership_line_names_the_federation_in_both_languages(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('Der DDHF ist Mitglied der')
            ->assertSee('https://ifhema.org/', false);

        $this->get('/en')->assertOk()
            ->assertSee('The DDHF is a member of')
            ->assertDontSee('Der DDHF ist Mitglied der')
            ->assertSee('https://ifhema.org/', false);

        // The old address is dead.
        $this->get('/')->assertDontSee('ifhema.com', false);
        $this->get('/en')->assertDontSee('ifhema.com', false);
    }

    public function test_both_languages_are_announced_to_search_engines(): void
    {
        $html = $this->get('/ranglisten')->assertOk()->getContent();

        $this->assertStringContainsString('hreflang="de" href="http://localhost/ranglisten"', $html);
        $this->assertStringContainsString('hreflang="en" href="http://localhost/en/standings"', $html);
    }

    public function test_dates_are_written_the_way_each_language_writes_them(): void
    {
        $date = now()->startOfYear()->addMonths(3);

        $this->get('/ddhf-turniere')->assertOk()->assertSee($date->format('d.m.Y'));
        $this->get('/en/ddhf-tournaments')->assertOk()->assertSee($date->format('j M Y'));
    }

    public function test_ranks_are_written_the_way_each_language_writes_them(): void
    {
        // A second place next to the first from setUp, so the suffix under test is one only
        // English has a rule for.
        Result::create([
            'tournament_id' => $this->tournament->id,
            'fencer_id'     => Fencer::create([
                'first_name' => 'Muster',
                'last_name'  => 'Fechter B',
                'is_active'  => true,
                'group_id'   => Group::first()->id,
            ])->id,
            'group_id'      => Group::first()->id,
            'federation_id' => Federation::first()->id,
            'placement'     => '2',
        ]);

        // Asserted on the cell rather than on the text: "2." and "2nd" both occur elsewhere on a
        // page full of numbers, and a loose match would pass on the wrong one.
        $this->get('/ddhf-turniere/' . $this->tournament->public_id)
            ->assertOk()
            ->assertSee('data-sort="2">2.</td>', false);

        $this->get('/en/ddhf-tournaments/' . $this->tournament->public_id)
            ->assertOk()
            ->assertSee('data-sort="2">2nd</td>', false)
            ->assertDontSee('data-sort="2">2.</td>', false);

        // The same result read from the other side: the list of individual results behind a name
        // in the standing.
        $this->get('/en/standings/' . $this->season->public_id)
            ->assertOk()
            ->assertSee('<td class="num">2nd</td>', false);
    }

    /** Where the language switch on a page points, as a path. */
    private function switchOn(string $path): string
    {
        preg_match(
            '#<a class="lang"\s+href="http://localhost([^"]*)"#s',
            $this->get($path)->assertOk()->getContent(),
            $found,
        );

        return ($found[1] ?? '') === '' ? '/' : $found[1];
    }
}
