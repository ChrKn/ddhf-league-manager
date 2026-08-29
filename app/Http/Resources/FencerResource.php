<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FencerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $fencer = [
            'id'            => $this->public_id,
            'title'         => $this->title,
            'first_name'    => $this->first_name,
            'last_name'     => $this->last_name,
            'nationality'   => $this->nationality,
            'date_of_birth' => $this->date_of_birth,
            'gender'        => $this->gender,
            // Null where the club asked not to be named. The fencer stays: the request was the
            // club's and says nothing about the people who fenced for it.
            'group'         => $this->group?->public_name,
        ];

        if ($request->path() === 'api/fencers') {
            return $fencer;
        }

        // A single fencer can be retrieved even when they are no longer active, because historical
        // standings keep referring to them. The flag tells consumers which case they are looking at.
        // No id either, or it would resolve to a club the API otherwise refuses to hand out.
        $fencer['group_id'] = $this->group?->isPublic() ? $this->group->public_id : null;
        $fencer['is_active'] = (bool) $this->is_active;

        return $fencer;
    }
}
