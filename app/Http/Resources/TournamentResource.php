<?php

namespace App\Http\Resources;

use App\Data\Regions;
use App\Standings\Placement;
use App\Standings\SeasonScoring;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TournamentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->public_id,
            'name'         => $this->name ?: $this->display_name,
            // Everybody who fenced at least one bout - the number the points are read at.
            'participants' => $this->participant_count,
            // How many had registered when registration closed. Evidence, never part of a point.
            'registered_count' => $this->registered_count,
            // Whether it counts towards the standing at all, decided where the standing decides it.
            'scored'       => (bool) SeasonScoring::of($this->season)?->scores($this->resource),
            // The grounds the federation scored it on although it fell short of the threshold.
            'scored_on_decision' => $this->isScoredOnDecision() ? $this->scored_on_decision : null,
            'decided_on'   => $this->isScoredOnDecision() ? $this->decided_on?->toDateString() : null,
            'region'       => $this->region,
            'region_name'  => Regions::label($this->region),
            // How it was fenced, as the organiser stated it. It no longer decides how to read the
            // placements below - each of those says for itself - but it is what a reader needs to
            // make sense of a list that names rounds instead of ranks.
            'format'       => $this->format,
            'ruleset'      => $this->ruleset?->name,
            'season_id'    => $this->season?->public_id,
            'season'       => $this->season?->display_name,
            // When it was fenced. Most tournaments say nothing and take the event's dates, so
            // these are answered rather than left to a consumer to work out - the event is still
            // below for anybody who needs to know which of the two was the source.
            'start_date'   => $this->held_from?->toDateString(),
            'end_date'     => $this->held_to?->toDateString(),
            'event'        => $this->event ? [
                'name'       => $this->event->name,
                'location'   => $this->event->location,
                'start_date' => $this->event->start_date,
                'end_date'   => $this->event->end_date,
            ] : null,
            // Ordered the way the points table reads, left to right, so the payload still comes
            // out as the result list of the tournament: the ranks, then the rounds from the
            // latest to the earliest, then whoever never left the pools. Sorted through
            // Placement rather than by the column, where "last-16" would precede "last-8".
            //
            // No points here: what a placement is worth depends on the scoring mode of the
            // season, and best_three may even discard it. That belongs to the standing.
            'results'      => ResultResource::collection(
                $this->whenLoaded('results', fn () => $this->results
                    ->sortBy(fn ($result) => Placement::from((string) $result->placement)->sortKey())
                    ->values())
            ),
        ];
    }
}
