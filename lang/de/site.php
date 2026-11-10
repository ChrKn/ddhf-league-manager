<?php

/*
 * The German wording of the public site. This is the original: every key here was a literal in a
 * Blade file before, and the English file is a translation of this one.
 *
 * What is deliberately absent: discipline, division and tournament format come out of the
 * database and stay in German in both languages until the federation says what they are called
 * in English. The same goes for the wording of Placement::label() and ScoringMode::label(),
 * which are federation vocabulary rather than interface text - and Placement::label() is also
 * what the API hands out, so translating it there would change an interface other people depend
 * on. See the README under "The road to v1".
 */

return [
    'brand_claim' => 'Ranglisten',
    'menu'        => 'Menü',
    'main_menu'   => 'Hauptmenü',

    'nav' => [
        'index'       => 'Übersicht',
        'standings'   => 'Ranglisten',
        'tournaments' => 'Turniere',
        'news'        => 'Meldungen',
        'search'      => 'Suche',
    ],

    'date_format'     => 'd.m.Y',
    'datetime_format' => 'd.m.Y, H:i',

    'language' => [
        'switch_to' => 'English',
        'label'     => 'Sprache',
    ],

    'index' => [
        'title'       => 'Ranglisten :year',
        'lede'        => 'Die ersten zehn jeder Rangliste der laufenden Saison.',
        'description' => 'Die aktuellen Ranglisten des Deutschen Dachverbands für historisches Fechten (DDHF): die ersten zehn jeder Waffe und Abteilung.',
        'all'         => 'Alle Ranglisten und früheren Jahre',
        'full'        => 'Vollständige Rangliste',
        'fencers'     => ':count Fechtende',
        'empty'       => 'Für :year sind noch keine gewerteten Ergebnisse eingetragen.',
        'empty_link'  => 'Die Ranglisten der Vorjahre',
        'news'        => 'Aktuelle Meldungen',
    ],

    'news' => [
        'title'       => 'Meldungen',
        'lede'        => '',
        'description' => 'Meldungen des DDHF zu den Ranglisten im historischen Fechten, etwa Ergebniskorrekturen.',
        'empty'       => 'In den letzten zwölf Monaten gab es keine Meldungen.',
        'revised'     => 'aktualisiert am :date',
        'all'         => 'Alle Meldungen',
        'new_tab'     => '(öffnet in neuem Tab)',
    ],

    'standings' => [
        'title'       => 'Ranglisten',
        'lede'        => 'Jede Waffe und Abteilung, für jedes Jahr, in dem sie gefochten wurde.',
        'description' => 'Alle Ranglisten des DDHF im historischen Fechten, nach Waffe, Abteilung und Jahr.',
        'table'       => 'Tabelle →',
        'empty'       => 'Keine Rangliste passt zu dieser Auswahl.',
        'count'       => 'Ranglisten',
    ],

    'standing' => [
        'season'           => 'Saison',
        'scoring'          => 'Auswertung',
        'back'             => '← alle Ranglisten',
        'points_scheme'    => 'Punktevergabe',
        'show'             => ':count anzeigen',
        'anonymised'       => 'anonymisiert',
        'not_counted'      => 'zählt nicht',
        'of_which_counted' => '· :count gewertet',
        'empty'            => 'In dieser Saison ist noch kein Ergebnis gewertet.',
        'empty_club'       => 'Aus dieser Gruppe ist hier niemand gewertet.',
        'count'            => 'Fechtende',
        'of_total'         => 'von :total in dieser Rangliste',
        'as_of'            => 'Stand nach :label',
        'to_current'       => 'zum aktuellen Stand',
        'up'               => '{1} einen Platz verbessert|[2,*] :count Plätze verbessert',
        'down'             => '{1} einen Platz verloren|[2,*] :count Plätze verloren',
        'same'             => 'unverändert',
        'new'              => 'neu',
        'new_long'         => 'neu in der Rangliste',
        'threshold'        => 'Gewertet ab',
        'threshold_value'  => ':count Angetretenen (Saisonregel)',
    ],

    'scoring' => [
        'lede'         => 'Die Punkte richten sich nach der Zahl der Teilnehmenden (Spalte) und dem Platz oder der Runde, in der jemand ausgeschieden ist (Zeile).',
        'used_in'      => 'Verwendet in',
        'place'        => 'Platz',
        'participants' => 'Teilnehmende',
        'pools'        => 'Ausgeschieden in den Pools',
        'from'         => 'ab :n',
        'up_to'        => 'bis :n',
        'threshold'    => 'Unter :count Teilnehmenden gibt es keine Punkte.',
        'own_threshold' => 'gewertet ab :count Angetretenen (Saisonregel)',
        'zero'         => '· = 0 Punkte',
        'empty'        => 'Für diese Punktevergabe ist keine Tabelle hinterlegt.',
    ],

    'tournaments' => [
        'title'       => 'Turniere',
        'lede'        => 'Alle Turniere, deren Ergebnisse in eine Rangliste des DDHF eingehen.',
        'description' => 'Alle Turniere im historischen Fechten, deren Ergebnisse in eine Rangliste des DDHF eingehen.',
        'empty'       => 'Kein Turnier passt zu dieser Auswahl.',
        'count'       => 'Turniere',
    ],

    'tournament' => [
        'back'       => '← alle Turniere',
        'standing'   => 'Rangliste dieser Saison',
        'results'    => 'Ergebnisse',
        'empty'      => 'Zu diesem Turnier ist kein Ergebnis erfasst.',
        'empty_club' => 'Aus dieser Gruppe ist hier niemand angetreten.',
        'count'      => 'Ergebnisse',
        'of_total'   => 'von :total',
        'note'       => 'Punkte gelten nur für Mitglieder des DDHF; die Rangliste zeigt, wer davon gewertet wird.',
    ],

    'columns' => [
        'rank'         => 'Platz',
        'change'       => 'Veränderung',
        'standing'     => 'Rangliste',
        'fencer'       => 'Fechter:in',
        'club'         => 'Gruppe',
        'points'       => 'Punkte',
        'qualifier'    => 'Gewertet über',
        'results'      => 'Einzelergebnisse',
        'discipline'   => 'Disziplin',
        'division'     => 'Abteilung',
        'year'         => 'Jahr',
        'tournaments'  => 'Turniere',
        'tournament'   => 'Turnier',
        'date'         => 'Datum',
        'format'       => 'Turniersystem',
        'participants' => 'Teilnehmende',
        'event'        => 'Veranstaltung',
        'location'     => 'Ort',
    ],

    'filters' => [
        'all'          => 'alle',
        'search'       => 'Suche',
        'hint'         => 'Name eingeben',
        'submit'       => 'Anzeigen',
        'reset'        => 'Zurücksetzen',
        'club'         => 'Gruppe',
        'step'         => 'Stand nach',
        'step_current' => 'aktuell: :label',
    ],

    'footer' => [
        'membership'      => 'Mitgliedschaft',
        'membership_text' => 'Der DDHF ist Mitglied der :link — der International Federation of Historical European Martial Arts.',
        'follow'          => 'Folgen',
        'imprint'         => 'Impressum',
        'privacy'         => 'Datenschutz',
        'version'         => 'Version :version',
    ],

    'changelog' => [
        'lede'        => 'Was sich von Version zu Version geändert hat. Nur auf Englisch.',
        'description' => 'Änderungen am Ranglistensystem des DDHF, Version für Version.',
    ],

    'search' => [
        'title'       => 'Person suchen',
        'lede'        => '',
        'label'       => 'Name',
        'placeholder' => 'Vor- oder Nachname',
        'submit'      => 'Suchen',
        'to_standing' => 'zur Rangliste →',
        'of'          => 'von :total',
        'no_club'     => 'Keine Gruppe',
        'too_short'   => 'Bitte mindestens :count Zeichen eingeben.',
        'nothing'     => 'Zu „:query“ steht niemand in den Ranglisten.',
        'trimmed'     => 'Mehr als :count Treffer — bitte den Namen genauer eingeben.',
        'count'       => '{1} eine Person|[2,*] :count Personen',
    ],
];
