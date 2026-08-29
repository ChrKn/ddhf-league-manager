<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\HttpFoundation\Response;

/**
 * `schedule:run`, reachable over HTTP, because this host has no crontab.
 *
 * The hosting control panel offers one kind of timer: fetch a URL every minute. That is the whole
 * reason this class exists - see the readme's *One thing on a timer* for what hangs off it and why
 * a queue is run this way at all. Everything below is the difference between a crontab entry and a
 * web request, and there are three of them.
 *
 * **It has a door.** A crontab entry is reachable by whoever can already log in; a URL is reachable
 * by everybody. Basic credentials, because that is what the control panel's form can send, and a
 * refusal is a 404 rather than a 401: an address that answers "wrong password" has confirmed it is
 * the right address.
 *
 * **It says nothing when things go well.** The panel mails whatever the request returns, once a
 * minute, to whoever is named in the form. Output on success would be 1440 mails a day and a filter
 * rule to hide them, which is how monitoring turns into noise nobody reads. Silence on success and
 * the error on failure makes that mail worth opening: it arrives when there is something to know.
 *
 * **It is on a clock it does not own.** A worker started from a crontab has the minute; started
 * from a request it has whatever the server allows before it cuts the connection. Hence
 * cron.worker_seconds - and hence the run being kept alive past a client that hangs up, so a
 * fetcher which gives up early does not take a half-finished export with it.
 *
 * **It has to say which PHP it means.** The scheduler starts queue:work as its own process, and
 * Symfony's finder takes the PHP_BINARY constant only when the current SAPI is cli. In a request it
 * is not, so the finder guesses - and on a host with several versions installed it guesses one this
 * application cannot run on. The subprocess then dies unheard, because the scheduler discards its
 * output, and schedule:run reports success over a queue that was never touched. Naming the binary
 * costs a line; finding this out costs an afternoon.
 */
class CronController extends Controller
{
    public function __invoke(Request $request): Response
    {
        if ($reason = $this->refusal($request)) {
            // Nobody is watching this address, and it answers 404 to everything on purpose - so
            // from outside, a password that does not match, a header the web server kept for
            // itself and a timer that never fires all look the same. This line is the difference,
            // and it names the case rather than the value.
            Log::warning('cron refused: ' . $reason);

            abort(404);
        }

        // Both may be refused on shared hosting, and neither is worth failing over: the run is
        // simply bounded by whatever the server does allow.
        @set_time_limit(0);
        @ignore_user_abort(true);

        $binary = $this->phpBinary();

        if ($binary === null) {
            return response(
                'Kein brauchbares PHP für die geplanten Befehle gefunden. CRON_PHP_BINARY setzen — '
                . 'den Pfad nennt php artisan deploy:check.' . PHP_EOL,
                500,
            );
        }

        // Read back by Symfony's finder before it starts guessing - see the class comment.
        putenv('PHP_BINARY=' . $binary);

        try {
            $status = Artisan::call('schedule:run');
            $output = Artisan::output();
        } catch (\Throwable $exception) {
            // The log because this is the one caller with nobody watching, the response because
            // the panel turns it into a mail.
            Log::error('schedule:run failed', ['exception' => $exception]);

            return response('schedule:run: ' . $exception->getMessage() . "\n", 500);
        }

        if ($status !== 0) {
            Log::error('schedule:run exited with ' . $status, ['output' => $output]);

            return response($output, 500);
        }

        return response()->noContent();
    }

    /**
     * The PHP the scheduled commands are to be started with, or null if there is none to be had.
     *
     * Configured wins, because a host that needs this setting is a host whose guess is wrong.
     * Where nothing is configured the finder is asked anyway rather than left to run inside the
     * scheduler: it is the same answer, arrived at early enough to say something about it.
     */
    private function phpBinary(): ?string
    {
        $binary = trim((string) config('cron.php_binary'));

        if ($binary === '') {
            $binary = (string) ((new PhpExecutableFinder())->find(false) ?: '');
        }

        // Checked rather than assumed: a path that is not there is the whole failure mode this
        // guards against, and it is one that otherwise reports success.
        return $binary !== '' && is_executable($binary) ? $binary : null;
    }

    /**
     * Why this request is not the control panel's, or null if it is.
     *
     * Compared with hash_equals rather than ==, which is habit more than necessity here: an
     * attacker who can time a route that runs the scheduler has easier things to do with it.
     */
    private function refusal(Request $request): ?string
    {
        $user = (string) config('cron.user');
        $password = (string) config('cron.password');

        // Without this, an unconfigured installation would let anybody in by also sending nothing -
        // the empty secret matching the empty header.
        if ($user === '' || $password === '') {
            return 'CRON_USER or CRON_PASSWORD is empty';
        }

        if ((string) $request->getUser() === '' && (string) $request->getPassword() === '') {
            // The interesting one. A control panel that was given credentials and a route that
            // never sees them means something in front of this application kept them - which is
            // what a directory protection on the same address does.
            return 'no credentials received - is the web server passing the Authorization header?';
        }

        if (!hash_equals($user, (string) $request->getUser())
            || !hash_equals($password, (string) $request->getPassword())) {
            return 'credentials did not match';
        }

        return null;
    }
}
