<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Everything that has to be true on a server, asked in one go.
 *
 * The readme's "on the day" list is a set of things somebody walks through and can skip a line of.
 * This is the same list, asked by something that does not get tired - and it matters most where you
 * can see the least, which on shared hosting is nearly everything.
 *
 * It judges rather than reports, and it exits non-zero when anything is wrong, so it can be hung in
 * a cron entry and heard from only when there is something to say. `php artisan about` already
 * prints the configuration; the point here is having an opinion about it.
 */
class CheckDeployment extends Command
{
    protected $signature = 'deploy:check';

    protected $description = 'Prüft, ob dieser Server so eingerichtet ist, wie er sein soll';

    /**
     * How long a job may lie in the queue untouched before that means something.
     *
     * Cron starts a worker every minute, so anything beyond a couple of minutes is not a busy
     * queue any more. The room is for the minutes withoutOverlapping skips while a long export
     * finishes, which is a queue working rather than a queue nobody serves.
     */
    private const QUEUE_GRACE = 5;

    /** @var list<array{0: string, 1: bool, 2: string}> label, passed, detail */
    private array $results = [];

    public function handle(): int
    {
        $this->php();
        $this->extensions();
        $this->application();
        $this->database();
        $this->writable();
        $this->mail();
        $this->queue();
        $this->assets();

        return $this->report();
    }

    private function php(): void
    {
        // The floor comes from composer.json rather than a number typed here, so the two cannot
        // drift apart.
        $required = json_decode(file_get_contents(base_path('composer.json')), true)['require']['php'] ?? '';
        $floor = ltrim($required, '^~>= ');

        $this->check(
            'PHP-Version',
            version_compare(PHP_VERSION, $floor, '>='),
            PHP_VERSION . ' (verlangt ' . $required . ', Binary ' . (PHP_BINARY ?: 'unbekannt') . ')',
        );
    }

    private function extensions(): void
    {
        $needed = $this->requiredExtensions();
        $missing = array_values(array_filter($needed, fn (string $e) => !extension_loaded($e)));

        $this->check(
            'PHP-Erweiterungen',
            $missing === [],
            $missing === [] ? count($needed) . ' vorhanden' : 'fehlt: ' . implode(', ', $missing),
        );
    }

    /**
     * Read out of composer.lock rather than typed here, so a new dependency's requirement is
     * checked without anybody remembering to add it - and so nothing is demanded that no package
     * actually asks for. `gd` sat in this list for a while on the strength of a comment in
     * Laravel's composer.json that turns out to be a *suggestion*, for a test helper this project
     * does not use.
     *
     * @return list<string>
     */
    private function requiredExtensions(): array
    {
        $lock = json_decode(file_get_contents(base_path('composer.lock')), true);

        // packages only, never packages-dev: pdo_sqlite is what the test suite runs on, and
        // demanding it on a server that speaks MySQL would be a check failing for no reason.
        $extensions = [];

        foreach ($lock['packages'] ?? [] as $package) {
            foreach (array_keys($package['require'] ?? []) as $requirement) {
                if (str_starts_with($requirement, 'ext-')) {
                    $extensions[] = substr($requirement, 4);
                }
            }
        }

        // Nobody declares this one, and nothing works without it.
        $extensions[] = 'pdo_mysql';

        sort($extensions);

        return array_values(array_unique($extensions));
    }

    private function application(): void
    {
        $this->check('Betriebsart', config('app.env') === 'production',
            'APP_ENV=' . config('app.env'));

        // The one that matters most. Laravel's error page shows the whole .env when this is on -
        // database password and mail password included, to anybody who can provoke an exception.
        $this->check('Fehlerseite aus', !config('app.debug'),
            config('app.debug') ? 'APP_DEBUG=true — zeigt die .env her!' : 'APP_DEBUG=false');

        $this->check('Anwendungsschlüssel', filled(config('app.key')),
            filled(config('app.key')) ? 'gesetzt' : 'APP_KEY fehlt');

        $url = (string) config('app.url');

        // Signed download routes and the link in the password mail are both built from this.
        $this->check('Adresse', str_starts_with($url, 'https://') && !str_ends_with($url, '/'),
            $url . (str_ends_with($url, '/') ? ' — Schrägstrich am Ende' : ''));
    }

    private function database(): void
    {
        try {
            DB::connection()->getPdo();
        } catch (\Throwable $exception) {
            $this->check('Datenbank', false, $exception->getMessage());

            return;
        }

        $this->check('Datenbank', true, 'erreichbar (' . DB::connection()->getDatabaseName() . ')');

        $pending = collect(app('migrator')->getMigrationFiles(database_path('migrations')))
            ->keys()
            ->diff(app('migrator')->getRepository()->getRan())
            ->count();

        $this->check('Migrationen', $pending === 0,
            $pending === 0 ? 'alle eingespielt' : "{$pending} ausstehend — php artisan migrate --force");
    }

    private function writable(): void
    {
        foreach (['storage' => storage_path(), 'bootstrap/cache' => base_path('bootstrap/cache')] as $label => $path) {
            $this->check("Schreibrecht {$label}", is_writable($path), $path);
        }
    }

    private function mail(): void
    {
        $mailer = config('mail.default');

        $this->check('Mailversand', $mailer !== 'log',
            $mailer === 'log'
                ? 'steht auf "log" — Passwortmails gehen nirgendwo hin'
                : $mailer . ', Absender ' . config('mail.from.address') . ' — mit mail:test prüfen');
    }

    private function queue(): void
    {
        // Not a nicety: the password reset is a queued job, so without this nothing arrives and the
        // page says it is on its way.
        if (!Schema::hasTable('jobs')) {
            $this->check('Warteschlange', false,
                'Verbindung ' . config('queue.default') . ', Tabelle jobs fehlt');

            return;
        }

        // The worker is started by cron, a minute at a time, so what there is to find out is not
        // whether a process is running - none is, between the minutes - but whether anything picks
        // the work up at all. A cron entry that never fires looks exactly like this from here.
        //
        // Waiting jobs only: a reserved one has a worker on it, and a worker that died mid-job is
        // the retry mechanism's business rather than this list's.
        $stale = DB::table('jobs')
            ->whereNull('reserved_at')
            ->where('available_at', '<', now()->subMinutes(self::QUEUE_GRACE)->getTimestamp())
            ->count();

        $this->check('Warteschlange', $stale === 0,
            $stale === 0
                ? 'Verbindung ' . config('queue.default')
                : ($stale === 1 ? 'Ein Job wartet' : "{$stale} Jobs warten")
                    . ' seit über ' . self::QUEUE_GRACE . ' Minuten — läuft schedule:run?');

        $failed = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;

        if ($failed > 0) {
            $this->check('Fehlgeschlagene Jobs', false, "{$failed} — php artisan queue:failed");
        }
    }

    private function assets(): void
    {
        $published = is_dir(public_path('js/filament')) || is_dir(public_path('css/filament'));

        $this->check('Panel-Dateien', $published,
            $published ? 'veröffentlicht' : 'fehlen — php artisan filament:assets');
    }

    private function check(string $label, bool $passed, string $detail): void
    {
        $this->results[] = [$label, $passed, $detail];
    }

    private function report(): int
    {
        $this->newLine();

        $width = max(array_map(fn (array $r) => mb_strlen($r[0]), $this->results));

        foreach ($this->results as [$label, $passed, $detail]) {
            // Padded by characters, not by sprintf - it counts bytes, and every umlaut in a label
            // would pull the column a step to the left.
            $padded = $label . str_repeat(' ', $width - mb_strlen($label) + 2);
            $line = '  ' . ($passed ? '✓' : '✗') . '  ' . $padded . ' ' . $detail;

            $passed ? $this->line($line) : $this->error($line);
        }

        $failed = count(array_filter($this->results, fn (array $r) => !$r[1]));

        $this->newLine();

        if ($failed === 0) {
            $this->info('Alles in Ordnung. Als Nächstes: php artisan mail:test <adresse>');

            return self::SUCCESS;
        }

        $this->error($failed === 1 ? 'Ein Punkt stimmt nicht.' : "{$failed} Punkte stimmen nicht.");

        // On a development machine most of these are supposed to be red. Saying so keeps the
        // command from becoming something people learn to ignore.
        if (config('app.env') === 'local') {
            $this->newLine();
            $this->line('Das hier ist ein Entwicklungsrechner — Betriebsart, Fehlerseite, Adresse und');
            $this->line('Mailversand sollen lokal genau so aussehen. Der Befehl ist für den Server gedacht.');
        }

        return self::FAILURE;
    }
}
