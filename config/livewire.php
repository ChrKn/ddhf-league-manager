<?php

/*
 * Only what differs from Livewire's own config. Livewire merges this file over its defaults one
 * top-level key at a time, so a key named here has to be given in full.
 */
return [

    'payload' => [
        'max_size' => 1024 * 1024,
        'max_nesting_depth' => 10,

        // Livewire's default is 50, and the import review goes past it on its own. Filament's
        // SelectColumn does not hand the select its initial state, so every filled select asks
        // the server for its label once the page is up - one call per row and column, all in the
        // same request. Two such columns (fencer, club) on a 32-row file made 54. The review can
        // show every row of an event at once, and the largest so far had 263 starters: 526.
        'max_calls' => 600,

        'max_components' => 200,
    ],

];
