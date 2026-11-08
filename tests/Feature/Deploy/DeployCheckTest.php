<?php

namespace Tests\Feature\Deploy;

use App\Support\Changelog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * The list of things that have to be true on a server, asked by something that does not get tired.
 *
 * Each test switches exactly one thing off, because the value of the command is naming which one -
 * a check that only ever says "something is wrong" is a check nobody acts on.
 */
class DeployCheckTest extends TestCase
{
    use RefreshDatabase;

    /**
     * git is never really asked here. The working copy's nearest tag is whatever was last tagged,
     * and between renaming "Unreleased" and tagging, a real call would turn the suite red.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->tag = 'v' . Changelog::version();

        // Read when git is asked rather than now, so a test can change the answer. Every process,
        // because the command goes in as an array and a pattern like "git describe*" does not match
        // it - and an unmatched command would quietly run for real.
        Process::preventStrayProcesses();
        Process::fake(['*' => fn () => $this->tag === null
            ? Process::result(exitCode: 128, errorOutput: 'fatal: not a git repository')
            : Process::result(output: $this->tag . "\n")]);
    }

    /** What `git describe --tags` answers; null for no repository. */
    private ?string $tag = null;

    protected function tearDown(): void
    {
        Changelog::usePath(null);

        parent::tearDown();
    }

    /** Everything a production server should look like, so a single change stands out. */
    private function asProduction(array $overrides = []): void
    {
        config(array_merge([
            'app.env'      => 'production',
            'app.debug'    => false,
            'app.key'      => 'base64:' . base64_encode(random_bytes(32)),
            'app.url'      => 'https://ranglisten.example.de',
            'mail.default' => 'smtp',
        ], $overrides));
    }

    public function test_a_server_set_up_properly_passes(): void
    {
        $this->asProduction();

        $this->artisan('deploy:check')->assertSuccessful();
    }

    public function test_the_error_page_being_on_fails_it(): void
    {
        $this->asProduction(['app.debug' => true]);

        // The most consequential of the lot: Laravel's error page shows the whole .env, database
        // password and mail password included, to anybody who can provoke an exception.
        $this->artisan('deploy:check')
            ->expectsOutputToContain('zeigt die .env her')
            ->assertFailed();
    }

    public function test_mail_going_nowhere_fails_it(): void
    {
        $this->asProduction(['mail.default' => 'log']);

        $this->artisan('deploy:check')
            ->expectsOutputToContain('Passwortmails gehen nirgendwo hin')
            ->assertFailed();
    }

    public function test_an_address_without_https_fails_it(): void
    {
        $this->asProduction(['app.url' => 'http://ranglisten.example.de']);

        // The link in the password mail and every signed download route are built from this.
        $this->artisan('deploy:check')->assertFailed();
    }

    public function test_a_trailing_slash_in_the_address_fails_it(): void
    {
        $this->asProduction(['app.url' => 'https://ranglisten.example.de/']);

        $this->artisan('deploy:check')->assertFailed();
    }

    public function test_running_in_the_wrong_mode_fails_it(): void
    {
        $this->asProduction(['app.env' => 'local']);

        $this->artisan('deploy:check')
            ->expectsOutputToContain('Entwicklungsrechner')
            ->assertFailed();
    }

    public function test_a_missing_application_key_fails_it(): void
    {
        $this->asProduction(['app.key' => '']);

        $this->artisan('deploy:check')->assertFailed();
    }

    /**
     * One job in the queue, waiting since the given number of minutes ago.
     *
     * Written straight into the table rather than dispatched, because what is being tested is what
     * the row looks like from outside - a dispatched job would have to be aged afterwards anyway.
     */
    private function jobWaitingSince(int $minutes, bool $reserved = false): void
    {
        DB::table('jobs')->insert([
            'queue'        => 'default',
            'payload'      => '{}',
            'attempts'     => 0,
            'reserved_at'  => $reserved ? now()->getTimestamp() : null,
            'available_at' => now()->subMinutes($minutes)->getTimestamp(),
            'created_at'   => now()->subMinutes($minutes)->getTimestamp(),
        ]);
    }

    public function test_a_job_nobody_picks_up_fails_it(): void
    {
        $this->asProduction();
        $this->jobWaitingSince(30);

        // The shape a missing cron entry takes from the outside: the worker is not a process one
        // can look for - between the minutes there is none - so the evidence is the work lying
        // there untouched.
        $this->artisan('deploy:check')
            ->expectsOutputToContain('läuft schedule:run?')
            ->assertFailed();
    }

    public function test_a_job_that_has_only_just_arrived_does_not(): void
    {
        $this->asProduction();
        $this->jobWaitingSince(1);

        // A queue with something in it is a queue being used. Only standing still means anything.
        $this->artisan('deploy:check')->assertSuccessful();
    }

    public function test_a_job_a_worker_is_already_on_does_not(): void
    {
        $this->asProduction();
        $this->jobWaitingSince(30, reserved: true);

        // An export can run longer than the grace period. That it was claimed is the point.
        $this->artisan('deploy:check')->assertSuccessful();
    }

    public function test_it_names_the_php_version_it_is_running_on(): void
    {
        $this->asProduction();

        // Which binary answered matters on shared hosting, where the shell and the website can sit
        // on different PHP versions and nothing says so.
        $this->artisan('deploy:check')
            ->expectsOutputToContain(PHP_VERSION)
            ->assertSuccessful();
    }

    public function test_a_changelog_that_does_not_match_the_tag_fails_it(): void
    {
        $this->asProduction();
        $this->tag = 'v99.0.0';

        $this->artisan('deploy:check')
            ->expectsOutputToContain('das letzte Tag ist v99.0.0')
            ->assertFailed();
    }

    public function test_no_repository_to_compare_with_is_not_a_fault(): void
    {
        $this->asProduction();
        $this->tag = null;

        $this->artisan('deploy:check')
            ->expectsOutputToContain('kein Tag zum Abgleich')
            ->assertSuccessful();
    }

    public function test_a_missing_changelog_fails_it(): void
    {
        $this->asProduction();
        Changelog::usePath(base_path('does-not-exist.md'));

        $this->artisan('deploy:check')
            ->expectsOutputToContain('CHANGELOG.md fehlt')
            ->assertFailed();
    }
}
