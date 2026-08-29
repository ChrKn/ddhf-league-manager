<?php

return [

    /*
     * Whether search engines may keep what they find here.
     *
     * False sends X-Robots-Tag: noindex, nofollow with every response - see
     * App\Http\Middleware\HideFromSearchEngines for why it is a header and not a line in
     * robots.txt.
     *
     * True is the state this site is meant to end up in, so that is the default: an installation
     * that says nothing is a live one. The server says otherwise until the federation has decided
     * the day it goes public.
     */

    'indexable' => filter_var(env('SITE_INDEXABLE', true), FILTER_VALIDATE_BOOLEAN),

];
