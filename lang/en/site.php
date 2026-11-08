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
    'menu'        => 'Menu',
    'main_menu'   => 'Main menu',

    'nav' => [
        'index'       => 'Overview',
        'standings'   => 'Standings',
        'tournaments' => 'Tournaments',
        'news'        => 'News',
        'search'      => 'Search',
    ],

    'date_format'     => 'j M Y',
    'datetime_format' => 'j M Y, H:i',

    'language' => [
        'switch_to' => 'Deutsch',
        'label'     => 'Language',
    ],

    'index' => [
        'title'       => 'Standings :year',
        'lede'        => 'The first ten of every standing of the current season.',
        'description' => 'The current standings of the German Federation for Historical Fencing (DDHF): the first ten of every weapon and division.',
        'all'         => 'All standings and earlier years',
        'full'        => 'Full standing',
        'fencers'     => ':count fencers',
        'empty'       => 'No scored results have been recorded for :year yet.',
        'empty_link'  => 'The standings of earlier years',
        'news'        => 'Latest news',
    ],

    'news' => [
        'title'       => 'News',
        'lede'        => '',
        'description' => 'Notices from the DDHF about its standings in historical fencing, such as corrected results.',
        'empty'       => 'There were no notices in the last twelve months.',
        'revised'     => 'updated :date',
        'all'         => 'All news',
        'new_tab'     => '(opens in a new tab)',
    ],

    'standings' => [
        'title'       => 'Standings',
        'lede'        => 'Every weapon and division, for every year it was fenced in.',
        'description' => 'Every DDHF standing in historical fencing, by weapon, division and year.',
        'table'       => 'Table →',
        'empty'       => 'No standing matches this selection.',
        'count'       => 'standings',
    ],

    'standing' => [
        'season'           => 'Season',
        'scoring'          => 'Scoring',
        'back'             => '← all standings',
        'points_scheme'    => 'Points',
        'show'             => 'show :count',
        'anonymised'       => 'anonymised',
        'not_counted'      => 'does not count',
        'of_which_counted' => '· :count counted',
        'empty'            => 'No result has been scored in this season yet.',
        'empty_club'       => 'Nobody from this group is scored here.',
        'count'            => 'fencers',
        'of_total'         => 'of :total in this standing',
        'as_of'            => 'Standings after :label',
        'to_current'       => 'current standings',
        'up'               => '{1} up one place|[2,*] up :count places',
        'down'             => '{1} down one place|[2,*] down :count places',
        'same'             => 'unchanged',
        'new'              => 'new',
        'new_long'         => 'new in this standing',
    ],

    'scoring' => [
        'lede'         => 'Points depend on the number of participants (column) and the place or round in which someone went out (row).',
        'used_in'      => 'Used in',
        'place'        => 'Place',
        'participants' => 'Participants',
        'pools'        => 'Out in the pools',
        'from'         => ':n+',
        'up_to'        => 'up to :n',
        'threshold'    => 'Below :count participants, nobody earns points.',
        'zero'         => '· = 0 points',
        'empty'        => 'No table has been entered for this scoring.',
    ],

    'tournaments' => [
        'title'       => 'Tournaments',
        'lede'        => 'Every tournament whose results count towards a DDHF standing.',
        'description' => 'Every historical fencing tournament whose results count towards a DDHF standing.',
        'empty'       => 'No tournament matches this selection.',
        'count'       => 'tournaments',
    ],

    'tournament' => [
        'back'       => '← all tournaments',
        'standing'   => 'Standing of this season',
        'results'    => 'Results',
        'empty'      => 'No result has been recorded for this tournament.',
        'empty_club' => 'Nobody from this group competed here.',
        'count'      => 'results',
        'of_total'   => 'of :total',
        'note'       => 'Points are awarded to members of the DDHF only; the standing shows which of them are scored.',
    ],

    'columns' => [
        'rank'         => 'Rank',
        'change'       => 'Change',
        'standing'     => 'Standing',
        'fencer'       => 'Fencer',
        'club'         => 'Group',
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
        'all'          => 'all',
        'search'       => 'Search',
        'hint'         => 'Enter a name',
        'submit'       => 'Show',
        'reset'        => 'Reset',
        'club'         => 'Group',
        'step'         => 'Standings after',
        'step_current' => 'current: :label',
    ],

    'footer' => [
        'membership'      => 'Membership',
        'membership_text' => 'The DDHF is a member of :link — the International Federation of Historical European Martial Arts.',
        'follow'          => 'Follow',
        'imprint'         => 'Imprint',
        'privacy'         => 'Privacy',
        'version'         => 'Version :version',
    ],

    'changelog' => [
        'lede'        => 'What changed from release to release.',
        'description' => 'Changes to the DDHF standings system, release by release.',
    ],

    'search' => [
        'title'       => 'Find a person',
        'lede'        => 'Type a name and see which standings it appears in, without knowing which one to open first.',
        'label'       => 'Name',
        'placeholder' => 'First or last name',
        'submit'      => 'Search',
        'to_standing' => 'to the standing →',
        'of'          => 'of :total',
        'no_club'     => 'No group',
        'too_short'   => 'Please type at least :count characters.',
        'nothing'     => 'Nobody matching “:query” appears in the standings.',
        'trimmed'     => 'More than :count matches — please narrow the name.',
        'count'       => '{1} one person|[2,*] :count people',
    ],
];
