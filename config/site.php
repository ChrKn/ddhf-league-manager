<?php

return [

    /*
     * Whether search engines may keep what they find here.
     *
     * False sends X-Robots-Tag: noindex, follow with every response - see
     * App\Http\Middleware\HideFromSearchEngines for why it is a header and not a line in
     * robots.txt.
     *
     * True is the state this site is meant to end up in, so that is the default: an installation
     * that says nothing is a live one. The server says otherwise until the federation has decided
     * the day it goes public.
     */

    'indexable' => filter_var(env('SITE_INDEXABLE', true), FILTER_VALIDATE_BOOLEAN),

    /*
     * The clock a time of day is shown in, wherever one is shown - so far only on announcements.
     *
     * The application itself runs on UTC and stores UTC, and stays that way: changing app.timezone
     * would shift how every stored timestamp is read. This is for display only. The federation and
     * nearly all of its readers are in Germany, so a quarter past three should say so rather than
     * a quarter past one.
     */

    'timezone' => 'Europe/Berlin',

];
