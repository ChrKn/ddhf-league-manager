<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SeasonResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * The parts of the display name are exposed as separate fields as well, so that consumers can
     * filter by discipline, division or year without having to parse the name.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->public_id,
            'name'         => $this->display_name,
            'year'         => (int) $this->year,
            'discipline'   => $this->standing?->discipline?->name,
            'division'     => $this->standing?->division?->name,
            'standing_id'  => $this->standing?->public_id,
            'scoring_mode' => $this->scoring_mode?->value,
        ];
    }
}
