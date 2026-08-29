<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FederationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {

        if ($request->path() === 'api/federations') {
            return [
                'id'             => $this->public_id,
                'name'           => $this->name,
            ];
        }
        return [
            'id'             => $this->public_id,
            'name'           => $this->name,
            // What the federation calls itself in English, where its own name does not travel.
            // Null is the normal case, not a gap.
            'english_name'   => $this->english_name,
            'abbreviation'   => $this->abbreviation,
            'country'        => $this->country,
            'website_url'    => $this->website_url,
            'logo_url'       => $this->logo_url,
            'is_active'      => (bool) $this->is_active,
        ];
    }
}
