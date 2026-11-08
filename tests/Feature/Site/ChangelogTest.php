<?php

namespace Tests\Feature\Site;

use App\Support\Changelog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The version in the footer and the page it links to, both read from CHANGELOG.md.
 */
class ChangelogTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Changelog::usePath(null);

        parent::tearDown();
    }

    /**
     * The file is edited by hand at every release, and the version is read off its first release
     * heading - so a heading that does not parse, or one out of order, would put a wrong number
     * in the footer without anything failing.
     */
    public function test_the_real_file_is_well_formed_and_newest_first(): void
    {
        $markdown = file_get_contents(base_path('CHANGELOG.md'));
        preg_match_all('/^## (.+)$/m', $markdown, $headings);

        $versions = [];

        foreach ($headings[1] as $index => $heading) {
            if ($heading === 'Unreleased') {
                $this->assertSame(0, $index, '"Unreleased" belongs at the top');
                continue;
            }

            $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+ - \d{4}-\d{2}-\d{2}$/', $heading);
            $versions[] = strtok($heading, ' ');
        }

        $this->assertNotEmpty($versions);

        for ($i = 1; $i < count($versions); $i++) {
            $this->assertTrue(version_compare($versions[$i - 1], $versions[$i], '>'),
                "{$versions[$i - 1]} should come before {$versions[$i]}");
        }

        $this->assertSame($versions[0], Changelog::version());
    }

    public function test_unreleased_changes_are_not_a_version(): void
    {
        $this->useFile("# Changelog\n\n## Unreleased\n\n- Something\n\n## 2.3.4 - 2026-01-01\n\n- Else\n");

        $this->assertSame('2.3.4', Changelog::version());
    }

    public function test_the_footer_shows_the_version_and_links_the_changelog_in_both_languages(): void
    {
        $this->useFile("# Changelog\n\n## 2.3.4 - 2026-01-01\n\n- Else\n");

        $this->get('/')->assertOk()
            ->assertSee('Version 2.3.4')
            ->assertSee('href="' . url('/changelog') . '"', false);

        $this->get('/en')->assertOk()
            ->assertSee('Version 2.3.4')
            ->assertSee('href="' . url('/en/changelog') . '"', false);
    }

    public function test_without_a_release_there_is_no_version_rather_than_a_guess(): void
    {
        $this->useFile("# Changelog\n\n## Unreleased\n\n- Something\n");

        $this->get('/')->assertOk()->assertDontSee('Version ', false);
    }

    public function test_the_page_is_the_file_in_english_with_an_anchor_per_release(): void
    {
        $this->useFile("# Changelog\n\nIntro.\n\n## 2.3.4 - 2026-01-01\n\n### Added\n- A thing with `code`\n");

        foreach (['/changelog', '/en/changelog'] as $path) {
            $this->get($path)->assertOk()
                ->assertSee('<div class="changelog" lang="en">', false)
                ->assertSee('<h2 id="2.3.4">2.3.4 - 2026-01-01</h2>', false)
                ->assertSee('<code>code</code>', false)
                // The file's own top heading gives way to the page's.
                ->assertDontSee('<h1>Changelog</h1>', false);
        }
    }

    public function test_it_is_not_indexed_and_not_in_the_sitemap(): void
    {
        config(['site.indexable' => true]);

        foreach (['/changelog', '/en/changelog'] as $path) {
            $this->get($path)->assertOk()
                ->assertSee('<meta name="robots" content="noindex,follow">', false);
        }

        $this->get('/sitemap.xml')->assertOk()->assertDontSee('changelog');
    }

    public function test_markup_in_the_file_is_not_passed_through(): void
    {
        $this->useFile("# Changelog\n\n## 2.3.4 - 2026-01-01\n\n- <script>alert(1)</script>\n");

        $this->get('/changelog')->assertOk()->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_no_file_no_page(): void
    {
        Changelog::usePath(base_path('does-not-exist.md'));

        $this->get('/changelog')->assertNotFound();
        $this->get('/')->assertOk();
    }

    private function useFile(string $markdown): void
    {
        $path = tempnam(sys_get_temp_dir(), 'changelog');
        file_put_contents($path, $markdown);
        Changelog::usePath($path);
    }
}
