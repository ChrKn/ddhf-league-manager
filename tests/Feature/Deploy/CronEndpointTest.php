<?php

namespace Tests\Feature\Deploy;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * The address that stands in for a crontab entry.
 *
 * It is the whole timer this application has - queue and export prune both hang off it - and it is
 * the one route reachable by anybody who guesses it, so both halves are worth pinning: that it
 * refuses without the credentials the control panel was given, and that it says nothing when the
 * run went well, because the panel mails whatever comes back.
 */
class CronEndpointTest extends TestCase
{
    private const USER = 'cron';

    private const PASSWORD = 'ein-langes-zufaelliges-geheimnis';

    private string|false $phpBinaryBefore = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->phpBinaryBefore = getenv('PHP_BINARY');
    }

    protected function tearDown(): void
    {
        // putenv outlives the request that made it, and would follow the rest of the suite around.
        $this->phpBinaryBefore === false
            ? putenv('PHP_BINARY')
            : putenv('PHP_BINARY=' . $this->phpBinaryBefore);

        parent::tearDown();
    }

    private function configured(): void
    {
        config(['cron.user' => self::USER, 'cron.password' => self::PASSWORD]);
    }

    public function test_it_runs_the_scheduler_and_says_nothing_about_it(): void
    {
        $this->configured();

        Artisan::shouldReceive('call')->once()->with('schedule:run')->andReturn(0);
        Artisan::shouldReceive('output')->andReturn("Running [queue:work]\n");

        $response = $this->withBasicAuth(self::USER, self::PASSWORD)->get('/cron');

        // 204 and an empty body on purpose: output once a minute is 1440 mails a day, and a
        // monitoring mail that always arrives is one nobody opens.
        $response->assertNoContent();
        $this->assertSame('', $response->getContent());
    }

    public function test_it_says_which_php_the_scheduler_is_to_start(): void
    {
        $this->configured();
        config(['cron.php_binary' => PHP_BINARY]);

        Artisan::shouldReceive('call')->once()->andReturn(0);
        Artisan::shouldReceive('output')->andReturn('');

        $this->withBasicAuth(self::USER, self::PASSWORD)->get('/cron')->assertNoContent();

        // The whole point of the setting: left to itself the finder ignores the PHP_BINARY constant
        // outside the cli SAPI and guesses, and a wrong guess fails where nobody is listening.
        $this->assertSame(PHP_BINARY, getenv('PHP_BINARY'));
    }

    public function test_a_php_that_is_not_there_is_said_out_loud_rather_than_tried(): void
    {
        $this->configured();
        config(['cron.php_binary' => '/usr/bin/php-das-es-nicht-gibt']);

        Artisan::shouldReceive('call')->never();

        $this->withBasicAuth(self::USER, self::PASSWORD)
            ->get('/cron')
            ->assertStatus(500)
            ->assertSee('CRON_PHP_BINARY');
    }

    public function test_a_failed_run_comes_back_as_something_to_read(): void
    {
        $this->configured();

        Artisan::shouldReceive('call')->once()->andReturn(1);
        Artisan::shouldReceive('output')->andReturn('Nichts ging.');

        $this->withBasicAuth(self::USER, self::PASSWORD)
            ->get('/cron')
            ->assertStatus(500)
            ->assertSee('Nichts ging.');
    }

    public function test_without_credentials_there_is_nothing_here(): void
    {
        $this->configured();

        Artisan::shouldReceive('call')->never();

        // 404 rather than 401: an address that answers "wrong password" has confirmed it is the
        // right address.
        $this->get('/cron')->assertNotFound();
    }

    public function test_the_wrong_password_is_no_better(): void
    {
        $this->configured();

        Artisan::shouldReceive('call')->never();

        $this->withBasicAuth(self::USER, 'geraten')->get('/cron')->assertNotFound();
    }

    public function test_a_refusal_says_in_the_log_which_of_the_two_it_was(): void
    {
        // All three refusals answer 404, which is right for whoever asked and useless for whoever
        // has to fix it: a wrong password and a header the web server kept for itself look exactly
        // alike from outside, and both look like the timer not firing at all.
        $this->configured();
        Log::spy();

        $this->get('/cron')->assertNotFound();

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn (string $message) => str_contains($message, 'no credentials received'));
    }

    public function test_and_says_it_differently_when_they_simply_did_not_match(): void
    {
        $this->configured();
        Log::spy();

        $this->withBasicAuth(self::USER, 'geraten')->get('/cron')->assertNotFound();

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn (string $message) => str_contains($message, 'did not match'));
    }

    public function test_an_unconfigured_installation_lets_nobody_in(): void
    {
        // The one that matters: without this, empty credentials would match an empty secret and
        // every installation that never set them would be running its scheduler for strangers.
        config(['cron.user' => null, 'cron.password' => null]);

        Artisan::shouldReceive('call')->never();

        $this->withBasicAuth('', '')->get('/cron')->assertNotFound();
        $this->get('/cron')->assertNotFound();
    }
}
