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
    'main_menu'   => 'Hauptmenü',

    'nav' => [
        'index'       => 'Übersicht',
        'standings'   => 'Ranglisten',
        'tournaments' => 'Turniere',
        'search'      => 'Suche',
    ],

    // Only the format, not a sentence: "9. Mai 2026" against "9 May 2026".
    'date_format' => 'd.m.Y',

    'language' => [
        'switch_to' => 'English',
        'label'     => 'Sprache',
    ],

    'index' => [
        'title'      => 'Ranglisten :year',
        'lede'       => 'Die ersten zehn jeder Rangliste der laufenden Saison.',
        'all'        => 'Alle Ranglisten und früheren Jahre',
        'full'       => 'Vollständige Rangliste',
        'fencers'    => ':count Fechter',
        'empty'      => 'Für :year sind noch keine gewerteten Ergebnisse eingetragen.',
        'empty_link' => 'Die Ranglisten der Vorjahre',
    ],

    'standings' => [
        'title' => 'Ranglisten',
        'lede'  => 'Jede Waffe und Abteilung, für jedes Jahr, in dem sie gefochten wurde.',
        'table' => 'Tabelle →',
        'empty' => 'Keine Rangliste passt zu dieser Auswahl.',
        'count' => 'Ranglisten',
    ],

    'standing' => [
        'season'      => 'Saison',
        'scoring'     => 'Auswertung',
        'all'         => 'alle Ranglisten',
        'show'        => ':count anzeigen',
        'anonymised'  => 'anonymisiert',
        'not_counted' => 'zählt nicht',
        // Shown only where not every result counts, so that the standard system says nothing
        // about a selection it never makes.
        'of_which_counted' => '· :count gewertet',
        'empty'       => 'In dieser Saison ist noch kein Ergebnis gewertet.',
        'empty_club'  => 'Aus diesem Verein ist hier niemand gewertet.',
        'count'       => 'Fechter',
        'of_total'    => 'von :total in dieser Rangliste',
    ],

    'tournaments' => [
        'title' => 'Turniere',
        'lede'  => 'Alle Turniere, deren Ergebnisse in eine Rangliste des DDHF eingehen.',
        'empty' => 'Kein Turnier passt zu dieser Auswahl.',
        'count' => 'Turniere',
    ],

    'tournament' => [
        'back'       => '← alle Turniere',
        'standing'   => 'Rangliste dieser Saison',
        'results'    => 'Ergebnisse',
        'empty'      => 'Zu diesem Turnier ist kein Ergebnis erfasst.',
        'empty_club' => 'Aus diesem Verein ist hier niemand angetreten.',
        'count'      => 'Ergebnisse',
        'of_total'   => 'von :total',
        'note'       => 'Punkte gelten nur für Mitglieder des DDHF; die Rangliste zeigt, wer davon gewertet wird.',
    ],

    'columns' => [
        'rank'         => 'Platz',
        'standing'     => 'Rangliste',
        'fencer'       => 'Fechter',
        'club'         => 'Verein',
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
        'participants' => 'Teilnehmer',
        'event'        => 'Veranstaltung',
        'location'     => 'Ort',
    ],

    'filters' => [
        'all'    => 'alle',
        'search' => 'Suche',
        'hint'   => 'Name eingeben',
        'submit' => 'Anzeigen',
        'reset'  => 'Zurücksetzen',
        'club'   => 'Verein',
    ],

    'footer' => [
        'membership'      => 'Mitgliedschaft',
        'membership_text' => 'Der DDHF ist Mitglied der :link — der International Federation of Historical European Martial Arts.',
        'follow'          => 'Folgen',
        'imprint'         => 'Impressum',
        'privacy'         => 'Datenschutz',
    ],

    'search' => [
        'title'       => 'Person suchen',
        'lede'        => 'Namen eintippen und sehen, in welchen Ranglisten er steht — ohne vorher zu wissen, in welcher.',
        'label'       => 'Name',
        'placeholder' => 'Vor- oder Nachname',
        'submit'      => 'Suchen',
        'to_standing' => 'zur Rangliste →',
        'of'          => 'von :total',
        'no_club'     => 'Kein Verein',
        'too_short'   => 'Bitte mindestens :count Zeichen eingeben.',
        // Gesucht wird in den Ranglisten, nicht in den Rohdaten: wer für einen Verein gefochten
        // hat, der kein Mitglied ist, steht in keiner Tabelle und deshalb auch hier nicht.
        'nothing'     => 'Zu „:query“ steht niemand in den Ranglisten.',
        'trimmed'     => 'Mehr als :count Treffer — bitte den Namen genauer eingeben.',
        'count'       => '{1} eine Person|[2,*] :count Personen',
    ],
];
