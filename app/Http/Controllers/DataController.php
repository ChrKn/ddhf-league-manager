<?php

namespace App\Http\Controllers;

use App\Models\Season;
use App\Models\Tournament;
use App\Standings\Placement;
use App\Standings\SeasonRanking;
use App\Standings\TournamentResults;
use App\Support\SiteUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * The public site once more, for machines: each list and each detail page as JSON, and llms.txt
 * to say what is there.
 *
 * What a page shows and nothing else, which is the whole design. The authenticated API is a
 * different thing - it hands out fencers, clubs and federations in their own right. This is a page
 * in another notation: the same released seasons, the same anonymised entries, the same clubs that
 * asked not to be named left out, and no public_id as a field, only inside an address.
 *
 * Per page and never in one piece, and never per person. A dump of everything, or everything about
 * one fencer, would put together with one request what the site deliberately does not - the same
 * reason there is no page per fencer and the search is kept out of the index.
 *
 * Language-neutral: one document per page, linked from both languages. The data is German either
 * way; what the pages translate - a placement - comes as its stored value plus both words.
 *
 * On the same switch as the sitemap: while the site is not meant to be found, none of this exists.
 */
class DataController extends Controller
{
    private const JSON_OPTIONS = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT;

    public function standings(): JsonResponse
    {
        abort_unless(config('site.indexable'), 404);

        $seasons = Season::with(['standing.discipline', 'standing.division'])
            ->withCount('tournaments')
            ->public()
            ->get()
            // As the page sorts them: newest year first, within a year by the standing's name.
            ->sortBy(fn (Season $season) => sprintf(
                '%04d|%s',
                9999 - (int) $season->year,
                $season->standing?->display_name ?? ''
            ))
            ->values();

        return $this->json('standings', [], 'Every released standing of the DDHF, one entry per year.', [
            'standings' => $seasons->map(fn (Season $season) => [
                'name'        => $season->standing?->display_name,
                'discipline'  => $season->standing?->discipline?->name,
                'division'    => $season->standing?->division?->name,
                'year'        => (int) $season->year,
                'tournaments' => (int) $season->tournaments_count,
                ...$this->links('standing', $season->public_id),
            ])->all(),
        ]);
    }

    public function standing(string $public_id): JsonResponse
    {
        abort_unless(config('site.indexable'), 404);

        $season = Season::with(SeasonRanking::relations())
            ->public()
            ->where('public_id', $public_id)
            ->firstOrFail();

        $ranking = SeasonRanking::for($season);

        return $this->json('standing', $public_id, 'One year of one DDHF standing, ranked.', [
            'name'         => $season->standing?->display_name,
            'discipline'   => $season->standing?->discipline?->name,
            'division'     => $season->standing?->division?->name,
            'year'         => (int) $season->year,
            'scoring_mode' => $ranking['mode'] ? [
                'code'  => $ranking['mode']->value,
                'label' => $ranking['mode']->label(),
            ] : null,
            'error'        => $ranking['error'],
            'rows'         => array_map(fn (array $row) => [
                'rank'       => $row['rank'],
                'name'       => $row['name'],
                'anonymized' => $row['anonymized'],
                'group'      => $row['group'],
                'points'     => $row['points'],
                'qualifier'  => $row['qualifier'],
                'results'    => array_map(fn (array $result) => [
                    'tournament' => $result['tournament'],
                    ...$this->links('tournament', $result['tournament_id']),
                    'placement'  => $this->placement(Placement::from($result['placement'])),
                    'points'     => $result['points'],
                    'counts'     => $result['counts'],
                ], $row['results']),
            ], $ranking['rows']),
        ]);
    }

    public function tournaments(): JsonResponse
    {
        abort_unless(config('site.indexable'), 404);

        $tournaments = Tournament::query()
            ->with(['event', 'season.standing.discipline', 'season.standing.division'])
            ->withCount('results')
            ->public()
            ->get()
            // As the page sorts them: newest season first, within it the latest tournament.
            ->sortByDesc(fn (Tournament $tournament) => [
                $tournament->season?->year ?? 0,
                $tournament->held_from?->timestamp ?? 0,
            ])
            ->values();

        return $this->json('tournaments', [], 'Every tournament that counts towards a released DDHF standing.', [
            'tournaments' => $tournaments->map(fn (Tournament $tournament) => [
                'name'         => $tournament->name ?: $tournament->display_name,
                'date'         => $this->dates($tournament),
                'standing'     => $tournament->season?->standing?->display_name,
                'year'         => $tournament->season ? (int) $tournament->season->year : null,
                'format'       => $tournament->format,
                'participants' => (int) $tournament->participant_count,
                'results'      => (int) $tournament->results_count,
                ...$this->links('tournament', $tournament->public_id),
            ])->all(),
        ]);
    }

    public function tournament(string $public_id): JsonResponse
    {
        abort_unless(config('site.indexable'), 404);

        $tournament = Tournament::where('public_id', $public_id)
            ->public()
            ->with(['event', 'season.standing.discipline', 'season.standing.division', ...TournamentResults::RELATIONS])
            ->firstOrFail();

        return $this->json('tournament', $public_id, 'One tournament of a released DDHF standing, with its full field.', [
            'name'         => $tournament->name ?: $tournament->display_name,
            'event'        => $tournament->event?->name,
            'location'     => $tournament->event?->location,
            'date'         => $this->dates($tournament),
            'discipline'   => $tournament->season?->standing?->discipline?->name,
            'division'     => $tournament->season?->standing?->division?->name,
            'format'       => $tournament->format,
            'participants' => $tournament->participant_count,
            'standing'     => $tournament->season ? [
                'name' => $tournament->season->standing?->display_name,
                'year' => (int) $tournament->season->year,
                ...$this->links('standing', $tournament->season->public_id),
            ] : null,
            'results'      => array_map(fn (array $result) => [
                'placement'  => $this->placement($result['placement']),
                'name'       => $result['fencer'],
                'anonymized' => $result['anonymized'],
                'group'      => $result['group'],
                'points'     => $result['points'],
            ], TournamentResults::for($tournament)),
        ]);
    }

    public function llms(): Response
    {
        abort_unless(config('site.indexable'), 404);

        return response()
            ->view('site.llms')
            ->header('Content-Type', 'text/markdown; charset=utf-8');
    }

    /* ----------------------------------------------------------------- helpers */

    /**
     * The same envelope on every document: what it is, the page it is a copy of, and when.
     *
     * @param  array<string, mixed>  $content
     */
    private function json(string $page, mixed $parameters, string $description, array $content): JsonResponse
    {
        return response()->json([
            'description'  => $description,
            'page'         => [
                'de' => SiteUrl::in($page, 'de', $parameters),
                'en' => SiteUrl::in($page, 'en', $parameters),
            ],
            'generated_at' => now()->toIso8601String(),
            ...$content,
        ], options: self::JSON_OPTIONS);
    }

    /**
     * Where a standing or a tournament can be read, as a page in either language and as JSON.
     *
     * @return array{page: array{de: string, en: string}, json: string}
     */
    private function links(string $page, string $publicId): array
    {
        return [
            'page' => [
                'de' => SiteUrl::in($page, 'de', $publicId),
                'en' => SiteUrl::in($page, 'en', $publicId),
            ],
            'json' => route("data.{$page}", $publicId),
        ];
    }

    /** @return array{code: string, de: string, en: string} */
    private function placement(Placement $placement): array
    {
        return [
            'code' => (string) $placement,
            'de'   => $placement->label('de'),
            'en'   => $placement->label('en'),
        ];
    }

    /**
     * ISO dates. "to" only where the tournament itself states a span - the same rule the pages
     * follow, rather than lending it the length of the event it was part of.
     *
     * @return array{from: string|null, to: string|null}
     */
    private function dates(Tournament $tournament): array
    {
        return [
            'from' => $tournament->held_from?->toDateString(),
            'to'   => $tournament->spansSeveralDays() ? $tournament->held_to->toDateString() : null,
        ];
    }
}
