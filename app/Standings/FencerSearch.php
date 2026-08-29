<?php

namespace App\Standings;

use App\Models\Federation;
use App\Models\Fencer;
use App\Models\Result;
use App\Models\Season;

/**
 * Finding a person without knowing which standing to look in.
 *
 * A reader who wants to know where they stand has to open the right table first, and there are
 * thirty-three of them. This answers the question the other way round: type a name, get the
 * standings that name appears in.
 *
 * **It may only surface what a standing already shows.** That is the whole difficulty. The raw
 * results say more than the published tables do - somebody who fenced for a club that is not a
 * member scores nothing and appears in no standing, and in a season that requires a category
 * declaration somebody who never made one appears in neither. Searching the results directly would
 * hand back exactly the people the tables leave out, so every hit is taken from
 * App\Standings\SeasonRanking, the same computation the pages themselves are built from.
 *
 * Anonymised fencers are not findable, and not by a special case: their name was deleted, so there
 * is nothing to match against. The guard against reaching them another way lives in the standing -
 * see SeasonRanking, which withholds an anonymised entry's tournaments.
 */
final class FencerSearch
{
    /** Below this, a search is a request for the whole membership list rather than a search. */
    public const MINIMUM = 2;

    /** More matches than a reader will read; the page says when it has trimmed. */
    public const LIMIT = 15;

    /**
     * @return array{people: list<array<string, mixed>>, total: int, trimmed: bool}
     */
    public function for(string $query): array
    {
        $query = trim($query);

        if (mb_strlen($query) < self::MINIMUM) {
            return ['people' => [], 'total' => 0, 'trimmed' => false];
        }

        $matches = $this->matching($query);
        $shown = $matches->take(self::LIMIT);

        // Once per season for everybody, rather than once per person for their seasons. Fifteen
        // people from the same handful of clubs share most of their seasons, and ranking a table
        // is the expensive part - doing it inside a loop over people meant doing it again for
        // each of them.
        $appearances = $this->appearancesIn($shown->pluck('id')->all());

        $people = [];

        foreach ($shown as $fencer) {
            // Somebody with results but no ranked appearance is somebody the tables do not list.
            // They stay unlisted here.
            if (($appearances[$fencer->id] ?? []) === []) {
                continue;
            }

            $rows = $appearances[$fencer->id];

            usort($rows, fn (array $a, array $b) => [$b['season']->year, $a['season']->display_name]
                <=> [$a['season']->year, $b['season']->display_name]);

            $people[] = [
                'name'        => $fencer->display_name,
                'group'       => $fencer->group?->public_name,
                'appearances' => $rows,
            ];
        }

        return [
            'people'  => $people,
            'total'   => count($people),
            'trimmed' => $matches->count() > self::LIMIT,
        ];
    }

    /** @return \Illuminate\Support\Collection<int, Fencer> */
    private function matching(string $query)
    {
        $needle = '%' . str_replace(['%', '_'], ['\%', '\_'], $query) . '%';

        $own = Federation::own();

        if ($own === null) {
            // Without our own federation on record nothing is scored and no table has anybody in
            // it, so there is nothing to find.
            return collect();
        }

        return Fencer::query()
            ->with('group')
            // Anonymised records hold no name at all, so they cannot match - but say so out loud
            // rather than relying on it.
            ->whereNull('anonymized_at')
            // Narrowed to people who could be in a table at all, before the limit applies rather
            // than after. Taking fifteen names and then dropping the ones no standing lists let
            // somebody alphabetically early and unranked push out somebody who is ranked, so a
            // search for a common fragment showed ten hits and claimed there were more.
            //
            // Not the whole answer - a season that requires a category declaration can still leave
            // somebody out - but that is settled per season, below, where it belongs.
            ->whereHas('results', fn ($sub) => $sub->where('federation_id', $own->id))
            ->where(function ($sub) use ($needle) {
                $sub->where('first_name', 'like', $needle)
                    ->orWhere('last_name', 'like', $needle)
                    // So that "Vorname Nachname" typed in full finds the person it obviously means.
                    ->orWhereRaw("CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) like ?", [$needle]);
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(self::LIMIT + 1)
            ->get();
    }

    /**
     * Where each of these people is ranked, keyed by fencer.
     *
     * @param  list<int>  $fencerIds
     * @return array<int, list<array<string, mixed>>>
     */
    private function appearancesIn(array $fencerIds): array
    {
        if ($fencerIds === []) {
            return [];
        }

        $wanted = array_flip($fencerIds);
        $found = [];

        foreach ($this->seasonsOf($fencerIds) as $season) {
            $ranking = SeasonRanking::for($season);

            foreach ($ranking['rows'] as $row) {
                if (!isset($wanted[$row['fencer']->id])) {
                    continue;
                }

                $found[$row['fencer']->id][] = [
                    'season' => $season,
                    'rank'   => $row['rank'],
                    'points' => $row['points'],
                    'of'     => count($ranking['rows']),
                ];
            }
        }

        return $found;
    }

    /**
     * The seasons worth computing at all - the ones any of these people fenced in.
     *
     * Without this the search would rank all thirty-three tables to answer one name.
     *
     * @param  list<int>  $fencerIds
     * @return \Illuminate\Support\Collection<int, Season>
     */
    private function seasonsOf(array $fencerIds)
    {
        $ids = Result::query()
            ->whereIn('fencer_id', $fencerIds)
            ->join('tournaments', 'tournaments.id', '=', 'results.tournament_id')
            ->distinct()
            ->pluck('tournaments.season_id');

        return Season::whereIn('id', $ids)->with(SeasonRanking::relations())->get();
    }
}
