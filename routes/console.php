<?php

use App\Models\Export;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Everything this application does on its own hangs off one thing on a timer, and nothing else.
 * On a host with a crontab that is a line in it:
 *
 *     * * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
 *
 * This one has no crontab, only a form that fetches a URL every minute, so the same thing happens
 * through App\Http\Controllers\CronController instead. The schedule below does not know which,
 * and does not need to.
 *
 * Without whichever it is nothing runs, and nothing tells you so - which is why deploy:check asks
 * whether anything is lying in the queue unclaimed, the shape a timer that never fires takes from
 * the outside.
 */

/*
 * Finished exports are deleted after four weeks, along with their files and the notification that
 * carried the download links - see App\Models\Export for why the retention lives there.
 */
Schedule::command('model:prune', ['--model' => [Export::class]])
    ->daily()
    ->description('Löscht Exporte, die älter als ' . Export::KEEP_FOR_WEEKS . ' Wochen sind');

/*
 * The queue worker, a minute at a time. Shared hosting has nowhere to keep a process running, so
 * instead of one worker that lives forever, cron starts one that drains what is there and exits.
 *
 * The application never notices the difference: same connection, same jobs table, same code doing
 * the dispatching. On a development machine this line does nothing at all, because nothing there
 * runs schedule:run - `composer dev` keeps a real `queue:listen` open instead, which reloads on a
 * code change and is what you want while writing a job.
 *
 * --stop-when-empty ends the run as soon as the queue is clear, --max-time keeps it from outliving
 * the next tick, and withoutOverlapping covers the case where an export takes longer than a minute.
 *
 * The minute is what a crontab entry can give it. This host has no crontab - the scheduler is
 * started by a web request instead, see App\Http\Controllers\CronController - and a request is cut
 * off long before a minute is up, which is why the budget comes from the environment.
 *
 * The ten minutes on that last one are not decoration. Its lock otherwise holds for a day, and a
 * worker that is killed rather than ended - which on shared hosting is a thing that happens, with
 * no say in it from here - never gets to release it. The queue would then stand still until
 * tomorrow, quietly. Ten minutes is longer than any run should take and short enough that nobody
 * finds out about it from a user.
 */
Schedule::command('queue:work --stop-when-empty --max-time=' . config('cron.worker_seconds'))
    ->everyMinute()
    ->withoutOverlapping(10)
    ->description('Arbeitet die Warteschlange ab: Import, Export, Passwortmails');
