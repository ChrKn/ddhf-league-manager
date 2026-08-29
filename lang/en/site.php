<?php

/*
 * The English wording of the public site.
 *
 * Interface text only. Discipline, division and tournament format are read from the database and
 * appear in German on this page too, because what they are called in English is the federation's
 * to say rather than ours to invent - the same decision already recorded for the four federations
 * that have no English name. The round names out of Placement::label() and the wording of
 * ScoringMode::label() stay German for the same reason: they are the points table's own headings,
 * and the federation has the word list.
 *
 * A rank is the exception, and deliberately so. "2." is not a term anybody has to agree on, it is
 * how German writes an ordinal, and in an English sentence that is wrong rather than untranslated -
 * so App\Support\Ordinal writes it "2nd" here. The API is not affected: it asks for German by
 * name, because placement_name is an interface other people depend on.
 */

return [
    'brand_claim' => 'Standings',
    'main_menu'   => 'Main menu',

    'nav' => [
        'index'       => 'Overview',
        'standings'   => 'Standings',
        'tournaments' => 'Tournaments',
        'search'      => 'Search',
    ],

    'date_format' => 'j M Y',

    'language' => [
        'switch_to' => 'Deutsch',
        'label'     => 'Language',
    ],

    'index' => [
        'title'      => 'Standings :year',
        'lede'       => 'The first ten of every standing of the current season.',
        'all'        => 'All standings and earlier years',
        'full'       => 'Full standing',
        'fencers'    => ':count fencers',
        'empty'      => 'No scored results have been recorded for :year yet.',
        'empty_link' => 'The standings of earlier years',
    ],

    'standings' => [
        'title' => 'Standings',
        'lede'  => 'Every weapon and division, for every year it was fenced in.',
        'table' => 'Table →',
        'empty' => 'No standing matches this selection.',
        'count' => 'standings',
    ],

    'standing' => [
        'season'      => 'Season',
        'scoring'     => 'Scoring',
        'all'         => 'all standings',
        'show'        => 'show :count',
        'anonymised'  => 'anonymised',
        'not_counted' => 'does not count',
        // Shown only where not every result counts, so that the standard system says nothing
        // about a selection it never makes.
        'of_which_counted' => '· :count counted',
        'empty'       => 'No result has been scored in this season yet.',
        'empty_club'  => 'Nobody from this club is scored here.',
        'count'       => 'fencers',
        'of_total'    => 'of :total in this standing',
    ],

    'tournaments' => [
        'title' => 'Tournaments',
        'lede'  => 'Every tournament whose results count towards a DDHF standing.',
        'empty' => 'No tournament matches this selection.',
        'count' => 'tournaments',
    ],

    'tournament' => [
        'back'       => '← all tournaments',
        'standing'   => 'Standing of this season',
        'results'    => 'Results',
        'empty'      => 'No result has been recorded for this tournament.',
        'empty_club' => 'Nobody from this club competed here.',
        'count'      => 'results',
        'of_total'   => 'of :total',
        'note'       => 'Points are awarded to members of the DDHF only; the standing shows which of them are scored.',
    ],

    'columns' => [
        'rank'         => 'Rank',
        'standing'     => 'Standing',
        'fencer'       => 'Fencer',
        'club'         => 'Club',
        'points'       => 'Points',
        'qualifier'    => 'Scored through',
        'results'      => 'Individual results',
        'discipline'   => 'Discipline',
        'division'     => 'Division',
        'year'         => 'Year',
        'tournaments'  => 'Tournaments',
        'tournament'   => 'Tournament',
        'date'         => 'Date',
        'format'       => 'Format',
        'participants' => 'Participants',
        'event'        => 'Event',
        'location'     => 'Location',
    ],

    'filters' => [
        'all'    => 'all',
        'search' => 'Search',
        'hint'   => 'Enter a name',
        'submit' => 'Show',
        'reset'  => 'Reset',
        'club'   => 'Club',
    ],

    'footer' => [
        'membership'      => 'Membership',
        'membership_text' => 'The DDHF is a member of :link — the International Federation of Historical European Martial Arts.',
        'follow'          => 'Follow',
        // The pages themselves are German and live on ddhf.de; these name the link, not the page.
        'imprint'         => 'Imprint',
        'privacy'         => 'Privacy',
    ],

    'search' => [
        'title'       => 'Find a person',
        'lede'        => 'Type a name and see which standings it appears in, without knowing which one to open first.',
        'label'       => 'Name',
        'placeholder' => 'First or last name',
        'submit'      => 'Search',
        'to_standing' => 'to the standing →',
        'of'          => 'of :total',
        'no_club'     => 'No club',
        'too_short'   => 'Please type at least :count characters.',
        'nothing'     => 'Nobody matching “:query” appears in the standings.',
        'trimmed'     => 'More than :count matches — please narrow the name.',
        'count'       => '{1} one person|[2,*] :count people',
    ],
];
