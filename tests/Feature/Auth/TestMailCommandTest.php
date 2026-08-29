<?php

namespace Tests\Feature\Auth;

use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The command that answers "does mail actually leave this machine".
 *
 * Only the refusal is worth testing here. Whether a message reaches an inbox depends on a
 * credential and a network that no test suite sees, which is the entire reason the command exists.
 */
class TestMailCommandTest extends TestCase
{
    public function test_it_refuses_while_mail_only_goes_to_the_log(): void
    {
        config(['mail.default' => 'log']);

        // The failure this exists to catch: everything looks like it worked, the log fills up, and
        // nobody notices until somebody cannot get back into their account.
        $this->artisan('mail:test', ['address' => 'niemand@example.test'])
            ->expectsOutputToContain('log')
            ->assertFailed();
    }

    public function test_it_sends_where_a_transport_is_configured(): void
    {
        Mail::fake();
        config(['mail.default' => 'smtp']);

        $this->artisan('mail:test', ['address' => 'niemand@example.test'])
            ->assertSuccessful();
    }

    public function test_it_names_the_configured_sender_before_sending(): void
    {
        Mail::fake();
        config([
            'mail.default'         => 'smtp',
            'mail.from.address'    => 'ranglisten@example.test',
            'mail.mailers.smtp.host' => 'smtp.example.test',
        ]);

        // Printed before the attempt, so a run against the wrong configuration is visible even
        // when the send itself succeeds.
        $this->artisan('mail:test', ['address' => 'niemand@example.test'])
            ->expectsOutputToContain('ranglisten@example.test')
            ->expectsOutputToContain('smtp.example.test')
            ->assertSuccessful();
    }
}
