<?php

namespace Tests\Feature\Auth;

use App\Filament\Auth\RequestPasswordReset;
use App\Models\User;
use Filament\Auth\Notifications\ResetPassword;
use Filament\Notifications\Livewire\Notifications as NotificationsComponent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The way back in for somebody who is not at a console.
 *
 * Until this existed, a forgotten password meant `php artisan tinker` - which is fine for whoever
 * built the thing and no use at all to anybody else. It is the piece readme point 3 waits on.
 *
 * What cannot be tested here is whether the message leaves the machine. That depends on a
 * credential and a network the test suite never sees, and `php artisan mail:test` is the answer
 * to it.
 */
class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $email = 'pruefung@example.test'): User
    {
        return User::create([
            'name'     => 'Prüferin',
            'email'    => $email,
            'password' => 'geheim',
        ]);
    }

    public function test_the_page_answers_without_a_login(): void
    {
        // It has to: somebody who could log in would not be here.
        $this->get('/verwaltung/password-reset/request')->assertOk();
    }

    public function test_a_known_address_is_sent_a_link(): void
    {
        Notification::fake();
        $user = $this->user();

        Livewire::test(RequestPasswordReset::class)
            ->fillForm(['email' => $user->email])
            ->call('request');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_the_link_leads_into_the_panel(): void
    {
        Notification::fake();
        $user = $this->user();

        Livewire::test(RequestPasswordReset::class)
            ->fillForm(['email' => $user->email])
            ->call('request');

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) {
            // Not Laravel's own /reset-password route, which this application does not serve.
            return str_contains($notification->url, '/verwaltung/password-reset/reset');
        });
    }

    public function test_the_message_is_in_german(): void
    {
        Notification::fake();
        $user = $this->user();

        Livewire::test(RequestPasswordReset::class)
            ->fillForm(['email' => $user->email])
            ->call('request');

        // Laravel builds this mail from JSON string keys, so without lang/de.json it arrives in
        // English and nobody notices until it is in somebody's inbox.
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $mail = $notification->toMail($user);

            return $mail->subject === 'Passwort zurücksetzen'
                && $mail->actionText === 'Passwort zurücksetzen'
                && str_contains($mail->introLines[0], 'Sie erhalten diese E-Mail');
        });
    }

    public function test_the_wrapping_around_the_message_is_german_too(): void
    {
        Notification::fake();
        $user = $this->user();

        Livewire::test(RequestPasswordReset::class)
            ->fillForm(['email' => $user->email])
            ->call('request');

        // The greeting, the sign-off and the "if the button does not work" line come from the
        // layout rather than from the notification, and only exist once it is rendered. Checking
        // toMail() alone would leave half the message untested and English.
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $rendered = $notification->toMail($user)->render();

            return str_contains($rendered, 'Guten Tag,')
                && str_contains($rendered, 'Viele Grüße')
                && str_contains($rendered, 'Adresszeile Ihres Browsers')
                && str_contains($rendered, 'Alle Rechte vorbehalten.')
                // Nothing English left anywhere in it, which is the assertion that catches the
                // next layout string somebody forgets rather than only the four named above.
                && ! str_contains($rendered, 'rights reserved')
                && ! str_contains($rendered, 'trouble clicking');
        });
    }

    public function test_an_unknown_address_is_told_nothing(): void
    {
        Notification::fake();
        $this->user();

        Livewire::test(RequestPasswordReset::class)
            ->fillForm(['email' => 'niemand@example.test'])
            ->call('request');

        $unknown = $this->shown();

        Notification::assertNothingSent();

        // And the page says exactly what it says to an address it does know. Filament answers a
        // miss with a red notification and a hit with a green one, which gives away which
        // addresses have an account here just as plainly as different wording would.
        Livewire::test(RequestPasswordReset::class)
            ->fillForm(['email' => 'pruefung@example.test'])
            ->call('request');

        $known = $this->shown();

        $this->assertNotSame([], $known, 'Ohne Meldung wäre der Vergleich wertlos.');
        $this->assertSame($known, $unknown);
    }

    public function test_being_throttled_is_still_said_out_loud(): void
    {
        Notification::fake();
        $user = $this->user();

        // Two are allowed per minute. The third has to be told to wait, or somebody sits watching
        // an inbox for a message that was never sent - that is not the same as hiding whether an
        // account exists.
        for ($attempt = 0; $attempt < 3; $attempt++) {
            Livewire::test(RequestPasswordReset::class)
                ->fillForm(['email' => $user->email])
                ->call('request');
        }

        $shown = $this->shown();

        // Filament counts the attempts itself, before the broker is ever asked, so this is its
        // wording and not lang/de/passwords.php. What matters is that it is its own answer: unlike
        // an unknown address, being throttled has to be distinguishable, or somebody waits for a
        // message that was never sent.
        $this->assertSame('danger', $shown[0]['status']);
        $this->assertStringContainsString('Versuche', $shown[0]['title']);
    }

    /**
     * What the page put on the screen, without the id that differs between any two of them.
     *
     * Read the way Filament itself reads them, through the component that displays them - which
     * takes them out of the session as it goes, so this answers for the last interaction only.
     *
     * @return list<array<string, mixed>>
     */
    private function shown(): array
    {
        $component = new NotificationsComponent();
        $component->mount();

        return $component->notifications
            ->map(fn ($notification): array => collect($notification->toArray())->except(['id'])->all())
            ->values()
            ->all();
    }
}
