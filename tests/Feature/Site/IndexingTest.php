<?php

namespace Tests\Feature\Site;

use Tests\TestCase;

/**
 * Whether this installation wants to be found.
 *
 * The site is finished before it is meant to be public, so there is a window where it answers to
 * anybody who knows the address and should still not turn up in a search result. That window is a
 * switch rather than an edit, because the way back has to be as small as the way there.
 */
class IndexingTest extends TestCase
{
    public function test_a_site_not_meant_to_be_found_says_so_on_every_page(): void
    {
        config(['site.indexable' => false]);

        $this->get('/')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_the_api_is_covered_by_the_same_switch(): void
    {
        // An unauthenticated 401 is enough here: what is being checked is that the header is put
        // on by something global rather than by the site's own routes.
        config(['site.indexable' => false]);

        $this->getJson('/api/standings')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_the_switch_comes_back_off(): void
    {
        config(['site.indexable' => true]);

        $this->get('/')->assertHeaderMissing('X-Robots-Tag');
    }

    public function test_crawlers_are_still_let_in(): void
    {
        // Not an oversight, and the reason the line above is a header at all: a crawler that is
        // turned away by robots.txt never reads the noindex, and an address it heard about
        // elsewhere can be listed anyway - without a description, which is worse than not being
        // listed. Shutting the door here would quietly undo the switch above.
        $robots = file_get_contents(public_path('robots.txt'));

        $this->assertStringNotContainsString('Disallow: /', $robots);
    }
}
