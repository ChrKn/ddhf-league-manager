<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StandingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if ($request->path() === 'api/standings') {
            return [
                'id'         => $this->public_id,
                'name'       => $this->display_name,
                'discipline' => $this->discipline?->name,
                'division'   => $this->division?->name,
            ];
        }

        return [
            'id'         => $this->public_id,
            'name'       => $this->display_name,
            'discipline' => $this->discipline?->name,
            'division'   => $this->division?->name,
            'seasons'    => $this->seasons
                ->sortByDesc('year')
                ->values()
                ->map(fn($season) => [
                    'id'   => $season->public_id,
                    'year' => (int) $season->year,
                ]),
        ];
    }
}
