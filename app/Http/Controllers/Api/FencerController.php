<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FencerResource;
use App\Models\Fencer;

class FencerController extends Controller
{
    public function index()
    {
        return FencerResource::collection(
            Fencer::query()
                ->with('group')
                ->where('is_active', true)
                // Anonymising also deactivates, so this is belt and braces - but the guarantee
                // should not depend on two flags agreeing with each other.
                ->whereNull('anonymized_at')
                ->get()
        );
    }

    /**
     * Deliberately not filtered by is_active: standings of past seasons refer to fencers who have
     * since been deactivated, and those references have to stay resolvable.
     *
     * Anonymised fencers are the exception. The API stopped handing out their public_id
     * anywhere, so as far as a consumer is concerned the resource does not exist.
     */
    public function show(string $public_id)
    {
        $fencer = Fencer::where('public_id', $public_id)
            ->with('group')
            ->first();

        if (!$fencer || $fencer->isAnonymized()) {
            return response()->json([
                'error' => 'Fencer not found.',
            ], 404);
        }

        return new FencerResource($fencer);
    }
}
