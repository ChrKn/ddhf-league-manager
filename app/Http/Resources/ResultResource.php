<?php

namespace App\Http\Resources;

use App\Models\Fencer;
use App\Standings\Placement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResultResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $fencer = $this->fencer;

        // The placement stays: it is a fact about the event, not a statement about the person.
        // Identity does not. The placeholder is derived from the flag rather than read from the
        // record, so the promise holds even for a record that was anonymised by hand and still
        // carries a leftover name.
        $anonymized = (bool) $fencer?->isAnonymized();

        $placement = Placement::from((string) $this->placement);

        return [
            // A string, and not always a number: a bracket result says which round it went out
            // in ("last-16"), a pool exit says "pools". Consumers that only want to display it
            // should read placement_name, which is that in German.
            'placement'      => $placement->value,
            'placement_name' => $placement->label(),
            'fencer'    => [
                'id'    => $anonymized ? null : $fencer?->public_id,
                'name'  => $anonymized ? Fencer::ANONYMOUS_NAME : $fencer?->display_name,
                // Always the club as it is named in the database, never the spelling a source
                // file happened to use. results.fencer_group_name keeps that raw spelling for
                // provenance, but the API must not hand out "ESK Starnberg" for a club that
                // is called "Europäische Schwertkunst".
                // Null where the person was anonymised, and null where the club asked not to be
                // named - the second changes nothing about the placement or the points.
                'group' => $anonymized ? null : $fencer?->group?->public_name,
            ],
        ];
    }
}
