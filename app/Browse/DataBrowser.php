<?php

namespace App\Browse;

use App\Data\Countries;
use App\Data\Regions;
use App\Filament\Pages\Browse\Events as EventsPage;
use App\Filament\Pages\Browse\Federations as FederationsPage;
use App\Filament\Pages\Browse\Fencers as FencersPage;
use App\Filament\Pages\Browse\Groups as GroupsPage;
use App\Filament\Pages\Browse\Ranking as RankingPage;
use App\Filament\Pages\Browse\Seasons as SeasonsPage;
use App\Filament\Pages\Browse\Standings as StandingsPage;
// Aliased throughout: half of these share a name with the model they list, and Tournament would
// otherwise mean the page here and the record two lines down.
use App\Filament\Pages\Browse\Tournament as TournamentPage;
use App\Filament\Pages\Browse\Tournaments as TournamentsPage;
use App\Models\Discipline;
use App\Models\Event;
use App\Models\Federation;
use App\Models\Fencer;
use App\Models\Group;
use App\Models\GroupLocation;
use App\Models\Result;
use App\Models\Season;
use App\Models\Standing;
use App\Models\Tournament;
use App\Standings\Placement;
use App\Standings\SeasonRanking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * A read-only browse over the whole data set, for checking it by eye.
 *
 * This exists because the admin panel shows one record at a time and the API answers in JSON,
 * and neither lets you scan a season for the one placement that looks wrong. Every foreign key
 * is resolved to the name it points at, because an id tells a reader nothing.
 *
 * Nothing here writes. It hands back the data a page needs rather than a response, because the
 * pages are Filament pages now - see App\Filament\Pages\Browse. That is also what gave it a
 * login: it used to answer at /tests to anyone who asked, and it shows every column the public
 * site withholds.
 */
class DataBrowser
{
    public function index()
    {
        return [
            'title'  => 'Übersicht',
            'counts' => [
                ['label' => 'Verbände',       'value' => Federation::count(),  'href' => FederationsPage::getUrl()],
                ['label' => 'Vereine',        'value' => Group::count(),       'href' => GroupsPage::getUrl()],
                ['label' => 'Fechter',        'value' => Fencer::count(),      'href' => FencersPage::getUrl()],
                ['label' => 'Veranstaltungen', 'value' => Event::count(),      'href' => EventsPage::getUrl()],
                ['label' => 'Turniere',       'value' => Tournament::count(),  'href' => TournamentsPage::getUrl()],
                ['label' => 'Ergebnisse',     'value' => Result::count(),      'href' => TournamentsPage::getUrl()],
                ['label' => 'Saisons',        'value' => Season::count(),      'href' => SeasonsPage::getUrl()],
                ['label' => 'Ranglisten',     'value' => Standing::count(),    'href' => StandingsPage::getUrl()],
            ],
            // A quick sanity panel: the things that are worth noticing at a glance rather than
            // having to go looking for.
            'notes' => [
                'Vereine ohne Land'          => Group::whereNull('country')->count(),
                'Vereine ohne Verband'       => Group::doesntHave('nationalFederations')->count(),
                'Fechter ohne Verein'        => Fencer::whereNull('group_id')->count(),
                'Fechter anonymisiert'       => Fencer::whereNotNull('anonymized_at')->count(),
                'Ergebnisse ohne Verein'     => Result::whereNull('group_id')->count(),
                'Ergebnisse ohne Verband'    => Result::whereNull('federation_id')->count(),
                'Turniere ohne Turniersystem' => Tournament::whereNull('format')->count(),
                'Veranstaltungen ohne Turnier' => Event::doesntHave('tournaments')->count(),
            ],
        ];
    }

    public function federations(Request $request)
    {
        // Over the pivot now, because a club can be counted under two federations at once.
        $groups_per_federation = DB::table('federation_group')
            ->select('federation_id', DB::raw('count(*) as aggregate'))
            ->groupBy('federation_id')
            ->pluck('aggregate', 'federation_id')
            ->all();
        $results_per_federation = $this->countBy(Result::query(), 'federation_id');

        $federations = Federation::query()
            ->when($request->query('country'), fn ($query, $country) => $query->where('country', $country))
            ->orderBy('name')
            ->get();

        return [
            'title'   => 'Verbände',
            'columns' => [
                'public_id'    => 'ID',
                'name'         => 'Name',
                'kind'         => 'Art',
                'english_name' => 'Englischer Name',
                'abbreviation' => 'Abkürzung',
                'country'      => 'Land',
                'website_url'  => 'Website',
                'is_active'    => 'Aktiv',
                'groups'       => 'Vereine',
                'results'      => 'Ergebnisse',
            ],
            'rows' => $federations->map(fn (Federation $federation) => [
                'public_id'    => $federation->public_id,
                'name'         => $federation->name,
                'kind'         => $federation->kind->label(),
                'english_name' => $federation->english_name,
                'abbreviation' => $federation->abbreviation,
                'country'      => Countries::label($federation->country),
                'website_url'  => $federation->website_url
                    ? ['text' => $federation->website_url, 'href' => $federation->website_url]
                    : null,
                'is_active'    => $federation->is_active ? 'ja' : 'nein',
                'groups'       => [
                    'text' => (int) ($groups_per_federation[$federation->id] ?? 0),
                    'href' => GroupsPage::getUrl(['federation' => $federation->public_id]),
                ],
                'results'      => (int) ($results_per_federation[$federation->id] ?? 0),
            ])->all(),
            'filters' => [
                'country' => [
                    'label'   => 'Land',
                    'options' => $this->countryOptions(Federation::query()),
                ],
            ],
        ];
    }

    public function groups(Request $request)
    {
        $fencers_per_group = $this->countBy(Fencer::query(), 'group_id');
        $results_per_group = $this->countBy(Result::query(), 'group_id');

        $groups = Group::query()
            ->with('federations', 'locations')
            ->when($request->query('country'), fn ($query, $country) => $query->where('country', $country))
            ->when($request->query('region'), function ($query, $region) {
                // "keiner" is a real answer: the clubs nobody has looked up are the ones worth
                // finding, and the column exists to make them findable.
                if ($region === 'none') {
                    return $query->doesntHave('locations');
                }

                return $query->whereHas('locations', fn ($sub) => $sub->where('region', $region));
            })
            ->when($request->query('federation'), function ($query, $federation) {
                // "keiner" is a real answer here, not a missing filter: 14 German clubs carry
                // results without a federation and finding them again has to stay easy. It asks
                // after a Dachverband, since being in a Verbund is not being in a federation.
                if ($federation === 'none') {
                    return $query->doesntHave('nationalFederations');
                }

                return $query->whereHas('federations', fn ($sub) => $sub->where('public_id', $federation));
            })
            ->orderBy('name')
            ->get();

        return [
            'title'   => 'Vereine',
            'columns' => [
                'public_id'    => 'ID',
                'name'         => 'Name',
                'abbreviation' => 'Abkürzung',
                'locations'    => 'Trainiert in',
                'country'      => 'Land',
                'federation'   => 'Verband',
                'website_url'  => 'Website',
                'is_active'    => 'Aktiv',
                'fencers'      => 'Fechter',
                'results'      => 'Ergebnisse',
            ],
            'rows' => $groups->map(fn (Group $group) => [
                'public_id'    => $group->public_id,
                'name'         => $group->name,
                'abbreviation' => $group->abbreviation,
                // Every town, not the first one: a club that trains in seven places says so.
                'locations'    => $group->locations->pluck('display_name')->implode(' · ') ?: null,
                'country'      => Countries::label($group->country),
                // Every one of them: a club in the DDHF and in INDES is in both, and picking one
                // to show is the thing this column stopped doing.
                'federation'   => $group->federations->pluck('name')->implode(' · ') ?: null,
                'website_url'  => $group->website_url
                    ? ['text' => $group->website_url, 'href' => $group->website_url]
                    : null,
                'is_active'    => $group->is_active ? 'ja' : 'nein',
                'fencers'      => [
                    'text' => (int) ($fencers_per_group[$group->id] ?? 0),
                    'href' => FencersPage::getUrl(['group' => $group->public_id]),
                ],
                'results'      => (int) ($results_per_group[$group->id] ?? 0),
            ])->all(),
            'filters' => [
                'federation' => [
                    'label'   => 'Verband',
                    'options' => $this->federationOptions(),
                ],
                'country' => [
                    'label'   => 'Land',
                    'options' => $this->countryOptions(Group::query()),
                ],
                'region' => [
                    'label'   => 'Bundesland',
                    'options' => $this->regionOptions(),
                ],
            ],
        ];
    }

    /**
     * The federal states clubs train in, plus the answer "none recorded" - which is what the
     * filter is really for, because a club without a place is a club nobody has looked up.
     *
     * @return array<string, string>
     */
    private function regionOptions(): array
    {
        $regions = GroupLocation::query()
            ->whereNotNull('region')
            ->distinct()
            ->orderBy('region')
            ->pluck('region')
            ->mapWithKeys(fn (string $region): array => [$region => $region])
            ->all();

        return $regions + ['none' => 'Kein Standort hinterlegt'];
    }

    public function fencers(Request $request)
    {
        $results_per_fencer = $this->countBy(Result::query(), 'fencer_id');

        $fencers = Fencer::query()
            ->with('group.federations')
            ->when($request->query('group'), function ($query, $group) {
                if ($group === 'none') {
                    return $query->whereNull('group_id');
                }

                return $query->whereHas('group', fn ($sub) => $sub->where('public_id', $group));
            })
            ->when($request->query('federation'), function ($query, $federation) {
                if ($federation === 'none') {
                    return $query->where(fn ($sub) => $sub
                        ->whereNull('group_id')
                        ->orWhereHas('group', fn ($group) => $group->doesntHave('nationalFederations')));
                }

                return $query->whereHas(
                    'group.federations',
                    fn ($sub) => $sub->where('public_id', $federation)
                );
            })
            ->when($request->query('status'), fn ($query, $status) => match ($status) {
                'active'     => $query->where('is_active', true),
                'inactive'   => $query->where('is_active', false),
                'anonymized' => $query->whereNotNull('anonymized_at'),
                default      => $query,
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        return [
            'title'   => 'Fechter',
            'columns' => [
                'public_id'   => 'ID',
                'name'        => 'Name',
                'birth_name'  => 'Geburtsname',
                'group'       => 'Verein',
                'federation'  => 'Verband',
                'nationality' => 'Nationalität',
                'gender'      => 'Geschlecht',
                'is_active'   => 'Aktiv',
                'anonymized'  => 'Anonymisiert',
                'results'     => 'Ergebnisse',
            ],
            'rows' => $fencers->map(fn (Fencer $fencer) => [
                'public_id'   => $fencer->public_id,
                'name'        => $fencer->display_name,
                'birth_name'  => $fencer->birth_name,
                'group'       => $fencer->group?->name,
                'federation'  => $fencer->group?->federation?->name,
                'nationality' => Countries::label($fencer->nationality),
                'gender'      => $this->genderLabel($fencer->gender),
                'is_active'   => $fencer->is_active ? 'ja' : 'nein',
                'anonymized'  => $fencer->isAnonymized()
                    ? $fencer->anonymized_at->format('d.m.Y')
                    : null,
                'results'     => (int) ($results_per_fencer[$fencer->id] ?? 0),
            ])->all(),
            'filters' => [
                'group' => [
                    'label'   => 'Verein',
                    'options' => $this->groupOptions(),
                ],
                'federation' => [
                    'label'   => 'Verband',
                    'options' => $this->federationOptions(),
                ],
                'status' => [
                    'label'   => 'Status',
                    'options' => [
                        'active'     => 'aktiv',
                        'inactive'   => 'inaktiv',
                        'anonymized' => 'anonymisiert',
                    ],
                ],
            ],
        ];
    }

    public function events(Request $request)
    {
        $events = Event::query()
            ->with('organizers')
            ->withCount('tournaments')
            ->when($request->query('year'), fn ($query, $year) => $query->whereYear('start_date', $year))
            ->orderByDesc('start_date')
            ->get();

        return [
            'title'   => 'Veranstaltungen',
            'columns' => [
                'public_id'   => 'ID',
                'name'        => 'Name',
                'start_date'  => 'Beginn',
                'end_date'    => 'Ende',
                'location'    => 'Ort',
                'organizers'  => 'Ausrichter',
                'tournaments' => 'Turniere',
            ],
            'rows' => $events->map(fn (Event $event) => [
                'public_id'   => $event->public_id,
                'name'        => $event->name,
                'start_date'  => $event->start_date?->format('d.m.Y'),
                'end_date'    => $event->end_date?->format('d.m.Y'),
                'location'    => $event->location,
                'organizers'  => $event->organizers->pluck('name')->implode(', '),
                'tournaments' => [
                    'text' => (int) $event->tournaments_count,
                    'href' => TournamentsPage::getUrl(['event' => $event->public_id]),
                ],
            ])->all(),
            'filters' => [
                'year' => [
                    'label'   => 'Jahr',
                    'options' => $this->eventYearOptions(),
                ],
            ],
        ];
    }

    public function tournaments(Request $request)
    {
        $tournaments = Tournament::query()
            ->with(['event', 'ruleset', 'season.standing.discipline', 'season.standing.division'])
            ->withCount('results')
            ->when($request->query('event'), fn ($query, $event) => $query->whereHas(
                'event',
                fn ($sub) => $sub->where('public_id', $event)
            ))
            ->when($request->query('season'), fn ($query, $season) => $query->whereHas(
                'season',
                fn ($sub) => $sub->where('public_id', $season)
            ))
            ->when($request->query('year'), fn ($query, $year) => $query->whereHas(
                'season',
                fn ($sub) => $sub->where('year', $year)
            ))
            ->when($request->query('discipline'), fn ($query, $discipline) => $query->whereHas(
                'season.standing.discipline',
                fn ($sub) => $sub->where('public_id', $discipline)
            ))
            ->when($request->query('format'), fn ($query, $format) => $query->where('format', $format))
            ->get()
            ->sortByDesc(fn (Tournament $tournament) => [
                $tournament->season?->year ?? 0,
                $tournament->held_from?->timestamp ?? 0,
            ]);

        return [
            'title'   => 'Turniere',
            // No tournament on record carries a name of its own - the imports never set one, and
            // a column of blanks would only be in the way. What identifies a tournament is the
            // event it was fenced at plus year, discipline and division, which is exactly what
            // Tournament::display_name spells out, so it is shown here taken apart into columns.
            'columns' => [
                'public_id'         => 'ID',
                'event'             => 'Veranstaltung',
                'year'              => 'Jahr',
                'discipline'        => 'Disziplin',
                'division'          => 'Abteilung',
                'format'            => 'Turniersystem',
                'ruleset'           => 'Regelwerk',
                'region'            => 'Zone',
                'participant_count' => 'Teilnehmer',
                'results'           => 'Ergebnisse',
            ],
            'rows' => $tournaments->map(fn (Tournament $tournament) => [
                'public_id'         => [
                    'text' => $tournament->public_id,
                    'href' => TournamentPage::getUrl(['public_id' => $tournament->public_id]),
                ],
                'event'             => [
                    'text' => $tournament->name ?: ($tournament->event?->name ?? '(ohne Namen)'),
                    'href' => TournamentPage::getUrl(['public_id' => $tournament->public_id]),
                ],
                'year'              => $tournament->season?->year,
                'discipline'        => $tournament->season?->standing?->discipline?->name,
                'division'          => $tournament->season?->standing?->division?->name,
                'format'            => $tournament->format,
                'ruleset'           => $tournament->ruleset?->name,
                'region'            => Regions::label($tournament->region),
                'participant_count' => (int) $tournament->participant_count,
                'results'           => (int) $tournament->results_count,
            ])->values()->all(),
            'filters' => [
                'year' => [
                    'label'   => 'Jahr',
                    'options' => $this->seasonYearOptions(),
                ],
                'discipline' => [
                    'label'   => 'Disziplin',
                    'options' => Discipline::orderBy('name')->pluck('name', 'public_id')->all(),
                ],
                'format' => [
                    'label'   => 'Turniersystem',
                    'options' => Tournament::whereNotNull('format')
                        ->distinct()
                        ->orderBy('format')
                        ->pluck('format', 'format')
                        ->all(),
                ],
                'event' => [
                    'label'   => 'Veranstaltung',
                    'options' => Event::orderBy('name')->pluck('name', 'public_id')->all(),
                ],
            ],
        ];
    }

    public function tournament(string $public_id)
    {
        $tournament = Tournament::where('public_id', $public_id)
            ->with([
                'event.organizers',
                'ruleset',
                'season.standing.discipline',
                'season.standing.division',
                'season.scoring_matrix',
                'results.fencer',
                'results.group',
                'results.federation',
            ])
            ->firstOrFail();

        // Points are never stored, so they have to be derived here exactly as the standings
        // derive them - otherwise this page would be checking itself rather than the data.
        $evaluator = SeasonRanking::evaluatorFor($tournament->season?->scoring_matrix);
        $participants = (int) $tournament->participant_count;

        // Ordered the way the points table reads, left to right: the ranks, then the rounds from
        // the latest to the earliest, then whoever never left the pools. Sorted through Placement,
        // because by the stored string "last-16" would come before "last-8".
        $results = $tournament->results
            ->sortBy(fn (\App\Models\Result $result) => [
                Placement::from((string) $result->placement)->sortKey(),
                $result->fencer?->display_name ?? '',
            ])
            ->map(fn (\App\Models\Result $result) => [
                'placement'         => Placement::from((string) $result->placement)->label(),
                'fencer'            => $result->fencer?->display_name,
                'group'             => $result->group?->name,
                'fencer_group_name' => $result->fencer_group_name,
                'federation'        => $result->federation?->name,
                'points'            => $evaluator?->pointsFor($participants, (string) $result->placement),
            ])
            ->values()
            ->all();

        return [
            'title'      => $tournament->name ?: $tournament->display_name,
            'tournament' => $tournament,
            'facts'      => [
                'ID'            => $tournament->public_id,
                'Veranstaltung' => $tournament->event?->name,
                'Ort'           => $tournament->event?->location,
                'Datum'         => $tournament->spansSeveralDays()
                    ? $tournament->held_from->format('d.m.Y') . ' – ' . $tournament->held_to->format('d.m.Y')
                    : $tournament->held_from?->format('d.m.Y'),
                'Saison'        => $tournament->season?->display_name,
                'Disziplin'     => $tournament->season?->standing?->discipline?->name,
                'Abteilung'     => $tournament->season?->standing?->division?->name,
                'Turniersystem' => $tournament->format,
                'Regelwerk'     => $tournament->ruleset?->name,
                'Zone'          => Regions::label($tournament->region),
                'Teilnehmer'    => $tournament->participant_count,
                'Punkteschlüssel' => $tournament->season?->scoring_matrix?->name,
            ],
            'columns' => [
                'placement'         => 'Platz',
                'fencer'            => 'Fechter',
                'group'             => 'Verein',
                'fencer_group_name' => 'Verein laut Quelle',
                'federation'        => 'Verband',
                'points'            => 'Punkte',
            ],
            'rows'    => $results,
            'ranking' => $tournament->season
                ? RankingPage::getUrl(['public_id' => $tournament->season->public_id])
                : null,
        ];
    }

    public function seasons(Request $request)
    {
        $seasons = Season::query()
            ->with(['standing.discipline', 'standing.division', 'scoring_matrix'])
            ->withCount('tournaments')
            ->when($request->query('year'), fn ($query, $year) => $query->where('year', $year))
            ->when($request->query('standing'), fn ($query, $standing) => $query->whereHas(
                'standing',
                fn ($sub) => $sub->where('public_id', $standing)
            ))
            ->orderByDesc('year')
            ->get();

        $results_per_season = DB::table('results')
            ->join('tournaments', 'results.tournament_id', '=', 'tournaments.id')
            ->select('tournaments.season_id', DB::raw('count(*) as aggregate'))
            ->groupBy('tournaments.season_id')
            ->pluck('aggregate', 'season_id')
            ->all();

        return [
            'title'   => 'Saisons',
            'columns' => [
                'public_id'    => 'ID',
                'year'         => 'Jahr',
                'standing'     => 'Rangliste',
                'discipline'   => 'Disziplin',
                'division'     => 'Abteilung',
                'scoring_mode' => 'Auswertung',
                'matrix'       => 'Punkteschlüssel',
                'tournaments'  => 'Turniere',
                'results'      => 'Ergebnisse',
                'ranking'      => 'Tabelle',
            ],
            'rows' => $seasons->map(fn (Season $season) => [
                'public_id'    => $season->public_id,
                'year'         => (int) $season->year,
                'standing'     => $season->standing?->display_name,
                'discipline'   => $season->standing?->discipline?->name,
                'division'     => $season->standing?->division?->name,
                'scoring_mode' => $season->scoring_mode?->label(),
                'matrix'       => $season->scoring_matrix?->name,
                'tournaments'  => [
                    'text' => (int) $season->tournaments_count,
                    'href' => TournamentsPage::getUrl(['season' => $season->public_id]),
                ],
                'results'      => (int) ($results_per_season[$season->id] ?? 0),
                'ranking'      => [
                    'text' => 'anzeigen',
                    'href' => RankingPage::getUrl(['public_id' => $season->public_id]),
                ],
            ])->all(),
            'filters' => [
                'year' => [
                    'label'   => 'Jahr',
                    'options' => $this->seasonYearOptions(),
                ],
                'standing' => [
                    'label'   => 'Rangliste',
                    'options' => Standing::with(['discipline', 'division'])
                        ->get()
                        ->sortBy(fn (Standing $standing) => $standing->display_name)
                        ->pluck('display_name', 'public_id')
                        ->all(),
                ],
            ],
        ];
    }

    public function standings()
    {
        $standings = Standing::query()
            ->with(['discipline', 'division', 'seasons'])
            ->get()
            ->sortBy(fn (Standing $standing) => $standing->display_name);

        return [
            'title'     => 'Ranglisten',
            'standings' => $standings,
        ];
    }

    /**
     * The table of one season, built by the same calculator the API uses.
     */
    public function ranking(string $public_id)
    {
        $season = Season::where('public_id', $public_id)
            ->with(SeasonRanking::relations())
            ->firstOrFail();

        // Shared with the public site, so that the two can never disagree about who came fourth
        // or about what an anonymised entry gives away.
        $ranking = SeasonRanking::for($season);

        return [
            'title'  => $season->display_name,
            'season' => $season,
            'error'  => $ranking['error'],
            'rows'   => $ranking['rows'],
            'mode'   => $ranking['mode'],
        ];
    }

    /* ----------------------------------------------------------------- helpers */

    /**
     * How many rows of a table fall on each value of a foreign key, keyed by that key.
     *
     * @return array<int, int>
     */
    private function countBy($query, string $column): array
    {
        return $query->getQuery()
            ->select($column, DB::raw('count(*) as aggregate'))
            ->whereNotNull($column)
            ->groupBy($column)
            ->pluck('aggregate', $column)
            ->all();
    }

    private function genderLabel(?string $gender): ?string
    {
        return match ($gender) {
            'male'   => 'männlich',
            'female' => 'weiblich',
            'divers' => 'divers',
            default  => $gender,
        };
    }

    /** @return array<string, string> */
    private function federationOptions(): array
    {
        return ['none' => 'ohne Verband'] + Federation::orderBy('name')
            ->pluck('name', 'public_id')
            ->all();
    }

    /** @return array<string, string> */
    private function groupOptions(): array
    {
        return ['none' => 'ohne Verein'] + Group::orderBy('name')
            ->pluck('name', 'public_id')
            ->all();
    }

    /**
     * Only the countries that actually occur, so the filter never offers an empty result.
     *
     * @return array<string, string>
     */
    private function countryOptions($query): array
    {
        return $query->whereNotNull('country')
            ->distinct()
            ->orderBy('country')
            ->pluck('country')
            ->mapWithKeys(fn (string $country) => [$country => Countries::label($country)])
            ->sort()
            ->all();
    }

    /** @return array<int, int> */
    private function seasonYearOptions(): array
    {
        return Season::distinct()
            ->orderByDesc('year')
            ->pluck('year', 'year')
            ->all();
    }

    /**
     * Grouped in PHP rather than with YEAR(), which SQLite - and so the test suite - does not
     * have. Forty-five events do not justify a database-specific query.
     *
     * @return array<int, int>
     */
    private function eventYearOptions(): array
    {
        return Event::whereNotNull('start_date')
            ->pluck('start_date')
            ->map(fn ($date) => (int) $date->format('Y'))
            ->unique()
            ->sortDesc()
            ->mapWithKeys(fn (int $year) => [$year => $year])
            ->all();
    }
}
