<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Passwortzeilen
    |--------------------------------------------------------------------------
    |
    | Was auf der Seite steht, nachdem jemand ein neues Passwort angefordert oder gesetzt hat.
    | Der Text der E-Mail selbst liegt woanders: sie besteht aus Zeichenkettenschlüsseln und
    | steht deshalb in lang/de.json.
    |
    | "sent" und "user" sagen mit Absicht dasselbe. Wer eine unbekannte Adresse eingibt, soll
    | daran nicht ablesen können, welche Adressen ein Konto haben - das ist eine Auskunft, die
    | eine Anmeldeseite nicht erteilen sollte, und Laravel gibt sie sonst.
    |
    */

    'reset' => 'Das Passwort wurde geändert.',
    'sent' => 'Falls es zu dieser Adresse ein Konto gibt, ist der Link unterwegs.',
    'throttled' => 'Bitte warten Sie einen Moment, bevor Sie es erneut versuchen.',
    'token' => 'Dieser Link ist abgelaufen oder wurde schon benutzt. Bitte fordern Sie einen neuen an.',
    'user' => 'Falls es zu dieser Adresse ein Konto gibt, ist der Link unterwegs.',

];
