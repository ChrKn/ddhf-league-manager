<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;

/**
 * Send one message and say what happened.
 *
 * The point is the day of the deployment: knowing that mail actually leaves the machine, without
 * triggering a real password reset to find out. It is the one part of the setup that cannot be
 * tested from here, because it depends on a credential and a network the tests never see.
 *
 * Sent straight rather than through the queue. A rejected login, a blocked port and a wrong app
 * password each fail differently, and the difference is the whole answer - put on the queue it
 * would become a failed job somewhere and the command would report success.
 */
class TestMail extends Command
{
    protected $signature = 'mail:test {address : Where to send it}';

    protected $description = 'Verschickt eine Testmail und sagt, was dabei herauskam';

    public function handle(): int
    {
        $mailer = config('mail.default');

        $this->newLine();
        $this->line('  Versand über  ' . $mailer);

        if ($mailer === 'smtp') {
            $this->line('  Server        ' . config('mail.mailers.smtp.host')
                . ':' . config('mail.mailers.smtp.port'));
            $this->line('  Anmeldung     ' . (config('mail.mailers.smtp.username') ?: '— keine —'));
        }

        $this->line('  Absender      ' . config('mail.from.name')
            . ' <' . config('mail.from.address') . '>');
        $this->newLine();

        // The failure this command exists to catch. Everything would look like it worked, the log
        // would fill up, and nobody would notice until somebody could not get back into their
        // account.
        if ($mailer === 'log') {
            $this->error('MAIL_MAILER steht auf "log" — die Mail landet in storage/logs und geht nirgendwo hin.');
            $this->line('Zum Prüfen des echten Versands MAIL_MAILER=smtp setzen; siehe .env.example.');

            return self::FAILURE;
        }

        $address = $this->argument('address');

        try {
            Mail::raw($this->body(), fn (Message $message) => $message
                ->to($address)
                ->subject('Testmail aus der DDHF-Ranglistenverwaltung'));
        } catch (\Throwable $exception) {
            $this->error('Der Versand ist gescheitert:');
            $this->newLine();
            $this->line('  ' . $exception->getMessage());
            $this->newLine();
            $this->hint();

            return self::FAILURE;
        }

        $this->info("Abgeschickt an {$address}.");
        $this->newLine();
        $this->line('Angekommen heißt noch nicht zugestellt: bitte im Postfach nachsehen, auch im Spam,');
        $this->line('und in den Kopfzeilen prüfen, ob spf, dkim und dmarc auf "pass" stehen.');

        return self::SUCCESS;
    }

    private function body(): string
    {
        return "Diese Nachricht kommt aus der DDHF-Ranglistenverwaltung.\n\n"
            . "Sie beweist zweierlei: der Versand ist eingerichtet, und diese Adresse nimmt ihn an.\n"
            . 'Steht in den Kopfzeilen bei spf, dkim und dmarc jeweils "pass", stimmt auch die Zustellbarkeit.';
    }

    /** The three ways this usually goes wrong, named so the message above can be placed. */
    private function hint(): void
    {
        $this->line('Die häufigsten Ursachen:');
        $this->line('  · Das Kontopasswort statt eines App-Passworts in MAIL_PASSWORD.');
        $this->line('  · App-Passwörter sind für das Konto nicht erlaubt, oder die Zwei-Faktor-Anmeldung fehlt.');
        $this->line('  · Der Hoster lässt ausgehende Verbindungen auf Port 587 nicht durch.');
    }
}
