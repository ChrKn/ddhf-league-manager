<?php

namespace Tests\Feature\Site;

use App\Models\Discipline;
use App\Models\Division;
use App\Models\Event;
use App\Models\ScoringMatrix;
use App\Models\Season;
use App\Models\Standing;
use App\Models\Tournament;
use App\Standings\ScoringMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every page of the site in both languages, each naming the other - and nothing while the site is
 * not meant to be found, because a sitemap of pages that ask to be left out is a contradiction.
 */
class SitemapTest extends TestCase
{
    use RefreshDatabase;

    private Season $season;

    private Tournament $tournament;

    protected function setUp(): void
    {
        parent::setUp();

        $this->season = Season::create([
            'year'              => (int) now()->year,
            'standing_id'       => Standing::create([
                'discipline_id' => Discipline::create(['name' => 'Langes Schwert'])->id,
                'division_id'   => Division::create(['name' => 'offen'])->id,
            ])->id,
            'scoring_matrix_id' => ScoringMatrix::create(['name' => 'Punkteschlüssel Test', 'matrix' => '[]'])->id,
            'scoring_mode'      => ScoringMode::Standard,
        ]);

        $this->tournament = Tournament::create([
            'season_id'         => $this->season->id,
            'event_id'          => Event::create(['name' => 'Musterturnier', 'start_date' => now()->toDateString()])->id,
            'participant_count' => 2,
        ]);
    }

    public function test_there_is_none_while_the_site_is_not_meant_to_be_found(): void
    {
        config(['site.indexable' => false]);

        $this->get('/sitemap.xml')->assertNotFound();
    }

    public function test_it_starts_with_the_declaration_and_no_view_carries_one(): void
    {
        config(['site.indexable' => true]);

        $this->assertStringStartsWith('<?xml version="1.0" encoding="UTF-8"?>' . "\n<urlset", $this->get('/sitemap.xml')->getContent());

        // short_open_tag cannot be switched on from a test, so this is checked at the source: on a
        // server that has it on, a "<?" anywhere in a view - echoed string or not - is a parse error.
        $views = \Illuminate\Support\Facades\File::allFiles(resource_path('views'));
        $this->assertNotEmpty($views);

        foreach ($views as $view) {
            $this->assertStringNotContainsString('<?xml', $view->getContents(), $view->getPathname());
        }
    }

    public function test_it_lists_every_page_in_both_languages(): void
    {
        config(['site.indexable' => true]);

        $response = $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=utf-8')
            ->assertCookieMissing('laravel_session');

        $locs = $this->locs($response->getContent());

        $this->assertEqualsCanonicalizing([
            'http://localhost',
            'http://localhost/en',
            'http://localhost/ranglisten',
            'http://localhost/en/standings',
            'http://localhost/turniere',
            'http://localhost/en/tournaments',
            "http://localhost/ranglisten/{$this->season->public_id}",
            "http://localhost/en/standings/{$this->season->public_id}",
            "http://localhost/turniere/{$this->tournament->public_id}",
            "http://localhost/en/tournaments/{$this->tournament->public_id}",
        ], $locs);
    }

    public function test_each_address_names_both_of_its_versions(): void
    {
        config(['site.indexable' => true]);

        $xml = $this->xml($this->get('/sitemap.xml')->getContent());

        foreach ($xml->url as $url) {
            $links = [];

            foreach ($url->children('http://www.w3.org/1999/xhtml')->link as $link) {
                // Unprefixed attributes are in no namespace, so not reachable as $link['hreflang']
                // on an element that was reached through one.
                $attributes = $link->attributes();
                $links[(string) $attributes['hreflang']] = (string) $attributes['href'];
            }

            $this->assertSame(['de', 'en'], array_keys($links));
            $this->assertContains((string) $url->loc, $links, 'An address has to be among its own alternates.');
        }

        $this->assertStringContainsString(
            '<xhtml:link rel="alternate" hreflang="en" href="http://localhost/en/standings"/>',
            $this->get('/sitemap.xml')->getContent()
        );
    }

    public function test_the_search_and_filtered_addresses_are_not_in_it(): void
    {
        config(['site.indexable' => true]);

        $content = $this->get('/sitemap.xml')->getContent();

        $this->assertStringNotContainsString('/suche', $content);
        $this->assertStringNotContainsString('/en/search', $content);

        foreach ($this->locs($content) as $loc) {
            $this->assertStringNotContainsString('?', $loc);
        }
    }

    /** @return list<string> */
    private function locs(string $content): array
    {
        $locs = [];

        foreach ($this->xml($content)->url as $url) {
            $locs[] = (string) $url->loc;
        }

        return $locs;
    }

    private function xml(string $content): \SimpleXMLElement
    {
        $xml = simplexml_load_string($content);

        $this->assertNotFalse($xml, 'The sitemap has to be well-formed XML.');

        return $xml;
    }
}
