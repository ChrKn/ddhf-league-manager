<?php

namespace Tests\Feature\Site;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The public site sets no cookie.
 *
 * Readers only look things up, so there is no session to keep and nothing to protect with a CSRF
 * token. A session here would cost a row in the sessions table per visitor, holding their IP
 * address, and a line in the privacy statement - for nothing.
 */
class CookieTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // phpunit.xml uses the array driver, which never sets a cookie anyway - the test would
        // pass with or without the session. Production keeps sessions in the database.
        config(['session.driver' => 'database']);
    }

    public function test_the_german_site_sets_no_cookie(): void
    {
        $this->assertNoTrace($this->get('/')->assertOk());
    }

    public function test_the_english_site_sets_no_cookie(): void
    {
        $this->assertNoTrace($this->get('/en')->assertOk());
    }

    public function test_a_page_that_is_not_there_sets_no_cookie_either(): void
    {
        $this->assertNoTrace($this->get('/ranglisten/does-not-exist')->assertNotFound());
    }

    public function test_the_panel_still_has_its_session(): void
    {
        $this->get('/verwaltung/login')
            ->assertCookie(config('session.cookie'))
            ->assertCookie('XSRF-TOKEN');

        $this->assertSame(1, DB::table('sessions')->count());
    }

    private function assertNoTrace(TestResponse $response): void
    {
        $response
            ->assertCookieMissing(config('session.cookie'))
            ->assertCookieMissing('XSRF-TOKEN');

        $this->assertSame(0, DB::table('sessions')->count());
    }
}
