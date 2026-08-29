<?php

return [

    /*
     * Who may start the scheduler over HTTP.
     *
     * The server this runs on has no crontab - only a form in the hosting control panel that
     * fetches a URL once a minute. So the one thing that drives this application is a web request,
     * and a web request is something anybody can make. These are the credentials that form sends
     * as HTTP Basic.
     *
     * Blank means the door is bricked up, not left open: with nothing configured the route answers
     * 404 to everybody, including whoever also sends nothing. That is what makes it safe to ship
     * this file with empty values and safe to have the route registered on a machine that never
     * uses it.
     */

    'user' => env('CRON_USER'),

    'password' => env('CRON_PASSWORD'),

    /*
     * How many seconds the queue worker may run in one go.
     *
     * A crontab entry can give it the whole minute it has. Started from a web request it gets
     * whatever the server allows a request to take, which is a good deal less and differs from
     * host to host - so the number belongs in the environment rather than in the schedule.
     */

    'worker_seconds' => (int) env('CRON_WORKER_SECONDS', 55),

    /*
     * Which PHP to start the scheduled commands with.
     *
     * schedule:run does not run queue:work itself, it starts it as its own process - and has to
     * know where PHP is to do that. Symfony's finder takes the PHP_BINARY constant only when the
     * current SAPI is cli, which in a web request it is not, so it falls back to guessing:
     * PHP_BINDIR, then the PATH. On a host that keeps several versions side by side, that guess is
     * a different PHP than the one this application runs on, and the subprocess dies without
     * anybody hearing it, because the scheduler throws its output away.
     *
     * Leave it empty where the guess is right - a machine with one PHP, or a real crontab, where
     * the SAPI is cli and the question never arises. `php artisan deploy:check` prints the path it
     * is running under, which is the one that belongs here.
     */

    'php_binary' => env('CRON_PHP_BINARY'),

];
