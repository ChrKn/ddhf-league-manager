<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GroupResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if ($request->path() === 'api/groups') {
            return [
                'id'             => $this->public_id,
                'name'           => $this->name,
            ];
        }

        return [
            'id'             => $this->public_id,
            'name'           => $this->name,
            'abbreviation'   => $this->abbreviation,
            'country'        => $this->country,
            // Where the club trains. A club may meet in several towns, so this is always a list,
            // and an empty one means nobody has looked it up rather than that there is no place.
            'locations'      => $this->locations->map(fn ($location): array => [
                'locality' => $location->locality,
                'region'   => $location->region,
                'country'  => $location->country,
            ])->values(),
            'website_url'    => $this->website_url,
            'logo_url'       => $this->logo_url,
            // Every federation the club is in, not one of them: it may be in a Dachverband and
            // a Verbund at once, and which is which is part of the answer. A federation that
            // asked not to be named is left out - that is the same two-flag question as always,
            // whether it still exists and whether it agreed to be shown here.
            'federations'    => $this->federations
                ->filter(fn ($federation): bool => $federation->isPublic())
                ->map(fn ($federation): array => [
                    'name' => $federation->name,
                    'kind' => $federation->kind->value,
                ])->values(),
            'is_active'      => (bool) $this->is_active,
        ];

    }
}
