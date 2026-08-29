<?php

namespace App\Http\Controllers;

use App\Models\Discipline;
use App\Models\Division;
use App\Models\Season;
use App\Models\Tournament;
use App\Standings\Placement;
use App\Standings\FencerSearch;
use App\Standings\SeasonRanking;
use App\Support\SiteUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

/**
 * The site the federation's readers see.
 *
 * Two rules run through every page here and are the reason it is a separate controller rather
 * than a friendlier skin on the data browser:
 *
 * No public_id and no database id is ever printed. They address pages, so they appear in links,
 * but a reader has no use for them and a column of them invites people to quote them as if they
 * meant something.
 *
 * Only what a standing is made of is shown - fencers, clubs, placements, points. There is no
 * page here for federations, clubs, fencers, events or seasons in their own right; the browser
 * under /tests has those, and they are not this site's business.
 */
class SiteController extends Controller
{
    /** How many rows the front page shows per standing. A hard cut, ties included. */
    private const FRONT_PAGE_ROWS = 10;

    /**
     * Every standing of the current year, cut to the first ten.
     *
     * The year comes from the calendar rather than from the data: in January the season has
     * started even if nobody has fenced yet. Standings with nothing in them are left out - an
     * empty table says less than its absence does.
     */
    public function index()
    {
        $year = (int) now()->year;

        $seasons = Season::with(SeasonRanking::relations())
            ->where('year', $year)
            ->get()
            ->sortBy(fn (Season $season) => $season->standing?->display_name ?? '')
            ->values();

        $tables = [];

        foreach ($seasons as $season) {
            $ranking = SeasonRanking::for($season);

            if ($ranking['rows'] === []) {
                continue;
            }

            $tables[] = [
                'title'     => $season->standing?->display_name ?? $season->display_name,
                'href'      => SiteUrl::to('standing', $season->public_id),
                // A hard cut at ten, even where the tenth and eleventh have the same points.
                // Showing eleven rows under a heading that promises ten is the worse surprise.
                'rows'      => array_slice($ranking['rows'], 0, self::FRONT_PAGE_ROWS),
                'total'     => count($ranking['rows']),
                'mode'      => $ranking['mode'],
            ];
        }

        return view('site.index', [
            'title'  => __('site.index.title', ['year' => $year]),
            'year'   => $year,
            'tables' => $tables,
        ]);
    }

    /**
     * The standings, each with the years it has been fenced in.
     */
    public function standings(Request $request)
    {
        $seasons = Season::with(['standing.discipline', 'standing.division'])
            ->withCount('tournaments')
            ->when($request->query('jahr'), fn ($query, $jahr) => $query->where('year', $jahr))
            ->when($request->query('disziplin'), fn ($query, $name) => $query->whereHas(
                'standing.discipline',
                fn ($sub) => $sub->where('name', $name)
            ))
            ->when($request->query('abteilung'), fn ($query, $name) => $query->whereHas(
                'standing.division',
                fn ($sub) => $sub->where('name', $name)
            ))
            ->get()
            // Newest year first, and within a year by the standing's name. As one sortable string
            // rather than two passes, because a second sort would undo the first.
            ->sortBy(fn (Season $season) => sprintf(
                '%04d|%s',
                9999 - (int) $season->year,
                $season->standing?->display_name ?? ''
            ))
            ->values();

        return view('site.standings', [
            'title'   => __('site.standings.title'),
            'rows'    => $seasons->map(fn (Season $season) => [
                'discipline'  => $season->standing?->discipline?->name,
                'division'    => $season->standing?->division?->name,
                'year'        => (int) $season->year,
                'tournaments' => (int) $season->tournaments_count,
                'href'        => SiteUrl::to('standing', $season->public_id),
            ])->all(),
            'filters' => $this->standingFilters(),
        ]);
    }

    /**
     * One year of one standing, in full.
     */
    public function standing(Request $request, string $public_id)
    {
        $season = Season::with(SeasonRanking::relations())
            ->where('public_id', $public_id)
            ->firstOrFail();

        $ranking = SeasonRanking::for($season);

        // The whole table is ranked first and narrowed afterwards, so a row keeps the rank it
        // earned against the full field. A club's fencers renumbered one to five would be a
        // different and untrue statement.
        $rows = $this->onlyClub($ranking['rows'], $request->query('verein'));

        return view('site.standing', [
            'title'   => $season->display_name,
            'season'  => $season,
            'rows'    => $rows,
            'total'   => count($ranking['rows']),
            'mode'    => $ranking['mode'],
            'error'   => $ranking['error'],
            'filters' => $this->clubFilter($ranking['rows']),
        ]);
    }

    /**
     * The tournaments that counted towards a standing.
     */
    public function tournaments(Request $request)
    {
        $tournaments = Tournament::query()
            ->with(['event', 'season.standing.discipline', 'season.standing.division'])
            ->withCount('results')
            ->when($request->query('jahr'), fn ($query, $jahr) => $query->whereHas(
                'season',
                fn ($sub) => $sub->where('year', $jahr)
            ))
            ->when($request->query('disziplin'), fn ($query, $name) => $query->whereHas(
                'season.standing.discipline',
                fn ($sub) => $sub->where('name', $name)
            ))
            ->when($request->query('abteilung'), fn ($query, $name) => $query->whereHas(
                'season.standing.division',
                fn ($sub) => $sub->where('name', $name)
            ))
            ->get()
            ->sortByDesc(fn (Tournament $tournament) => [
                $tournament->season?->year ?? 0,
                $tournament->held_from?->timestamp ?? 0,
            ])
            ->values();

        return view('site.tournaments', [
            'title'   => __('site.tournaments.title'),
            'rows'    => $tournaments->map(fn (Tournament $tournament) => [
                'name'        => $tournament->name ?: $tournament->display_name,
                'date'        => $this->dateOf($tournament),
                // Sorted by, not shown: "09.05.2026" read as a number is nine.
                'date_sort'   => $tournament->held_from?->format('Y-m-d'),
                'format'      => $tournament->format,
                'participants' => (int) $tournament->participant_count,
                'results'     => (int) $tournament->results_count,
                'href'        => SiteUrl::to('tournament', $tournament->public_id),
            ])->all(),
            'filters' => $this->standingFilters(),
        ]);
    }

    /**
     * One tournament with its full field.
     */
    public function tournament(Request $request, string $public_id)
    {
        $tournament = Tournament::where('public_id', $public_id)
            ->with([
                'event',
                'season.standing.discipline',
                'season.standing.division',
                'season.scoring_matrix',
                'results.fencer',
                'results.group',
            ])
            ->firstOrFail();

        $evaluator = SeasonRanking::evaluatorFor($tournament->season?->scoring_matrix);
        $participants = (int) $tournament->participant_count;

        // Ordered the way a result list reads: the ranks, then the rounds from the latest to the
        // earliest, then whoever never left the pools. Through Placement, because sorted as text
        // "last-16" would come before "last-8".
        $results = $tournament->results
            ->sortBy(fn ($result) => [
                Placement::from((string) $result->placement)->sortKey(),
                $result->fencer?->display_name ?? '',
            ])
            ->map(function ($result) use ($evaluator, $participants) {
                $anonymized = (bool) $result->fencer?->isAnonymized();

                $placement = Placement::from((string) $result->placement);

                return [
                    'placement' => $placement->label(App::getLocale()),
                    // The order the table itself reads in: ranks, then rounds from the latest to
                    // the earliest, then the pools. "4tel Finale" sorted as text lands under 4.
                    'placement_sort' => $placement->sortKey(),
                    'fencer'    => $result->fencer?->display_name,
                    // The club as the database names it, and nothing at all for an anonymised
                    // entry or a club that asked not to be shown. The raw spelling from the
                    // source file stays out of this site either way.
                    'group'  => $anonymized ? null : $result->group?->public_name,
                    'points' => $evaluator?->pointsFor($participants, (string) $result->placement),
                ];
            })
            ->values()
            ->all();

        return view('site.tournament', [
            'title'      => $tournament->name ?: $tournament->display_name,
            'tournament' => $tournament,
            'rows'       => $this->onlyClub($results, $request->query('verein')),
            'total'      => count($results),
            'filters'    => $this->clubFilter($results),
            'facts'      => array_filter([
                __('site.columns.event')     => $tournament->event?->name,
                __('site.columns.location')  => $tournament->event?->location,
                __('site.columns.date')      => $this->dateOf($tournament),
                __('site.columns.discipline') => $tournament->season?->standing?->discipline?->name,
                __('site.columns.division')   => $tournament->season?->standing?->division?->name,
                __('site.columns.format')     => $tournament->format,
                __('site.columns.participants') => $tournament->participant_count,
            ], fn ($value) => $value !== null && $value !== ''),
            'standing' => $tournament->season
                ? SiteUrl::to('standing', $tournament->season->public_id)
                : null,
        ]);
    }

    /* ----------------------------------------------------------------- helpers */

    /**
     * When a tournament was fenced, written for a reader.
     *
     * A span only where the tournament itself states one. Falling back to a two-day event would
     * otherwise print that span against each of its six tournaments and claim something nobody
     * established - the event lasted two days, this tournament may well have lasted an afternoon.
     */
    /**
     * Find a person without knowing which standing to look in.
     *
     * Deliberately not a page per fencer. The hits point into the standings the person appears in,
     * which is what a reader wants and stops short of publishing a page that reads as a dossier
     * about somebody. `noindex` for the same reason - findable on the site, not through a search
     * engine, and search result pages have no business being indexed anyway.
     */
    public function search(Request $request, FencerSearch $search)
    {
        $query = (string) $request->query('q', '');
        $found = $search->for($query);

        return view('site.search', [
            'title'   => __('site.search.title'),
            'query'   => $query,
            'people'  => $found['people'],
            'total'   => $found['total'],
            'trimmed' => $found['trimmed'],
            'tooShort' => $query !== '' && mb_strlen(trim($query)) < FencerSearch::MINIMUM,
            'noindex' => true,
        ]);
    }

    private function dateOf(Tournament $tournament): ?string
    {
        $from = $tournament->held_from?->format(__('site.date_format'));

        if (!$tournament->spansSeveralDays()) {
            return $from;
        }

        return $from . ' – ' . $tournament->held_to->format(__('site.date_format'));
    }

    /**
     * Narrow a set of already ranked rows to one club.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function onlyClub(array $rows, ?string $club): array
    {
        if ($club === null || $club === '') {
            return $rows;
        }

        return array_values(array_filter($rows, fn (array $row) => ($row['group'] ?? null) === $club));
    }

    /**
     * The clubs that occur on this page, and only those - a dropdown of every club on record
     * would mostly offer empty answers.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, array{label: string, options: array<string, string>}>
     */
    private function clubFilter(array $rows): array
    {
        $clubs = collect($rows)
            ->pluck('group')
            ->filter()
            ->unique()
            ->sort(SORT_LOCALE_STRING)
            ->mapWithKeys(fn (string $name) => [$name => $name])
            ->all();

        return ['verein' => ['label' => __('site.filters.club'), 'options' => $clubs]];
    }

    /**
     * The filters the two list pages share. Only values that actually occur, so a filter can
     * never lead to an empty page.
     *
     * @return array<string, array{label: string, options: array<string, string>}>
     */
    private function standingFilters(): array
    {
        return [
            'jahr' => [
                'label'   => __('site.columns.year'),
                'options' => Season::distinct()
                    ->orderByDesc('year')
                    ->pluck('year', 'year')
                    ->map(fn ($year) => (string) $year)
                    ->all(),
            ],
            'disziplin' => [
                'label'   => __('site.columns.discipline'),
                'options' => Discipline::orderBy('name')->pluck('name', 'name')->all(),
            ],
            'abteilung' => [
                'label'   => __('site.columns.division'),
                'options' => Division::orderBy('name')->pluck('name', 'name')->all(),
            ],
        ];
    }
}
