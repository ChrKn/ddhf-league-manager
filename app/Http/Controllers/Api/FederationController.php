<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FederationResource;
use App\Models\Federation;

class FederationController extends Controller
{
    public function index()
    {
        return FederationResource::collection(Federation::public()->get());
    }

    /**
     * As with clubs: a federation that asked not to be shown is not resolvable here. What it
     * does not touch is Federation::own(), which decides whose members score points - that has
     * to keep working whatever anybody asked for.
     */
    public function show(string $public_id)
    {
        $federation = Federation::public()->where('public_id', $public_id)->first();

        if (!$federation) {
            return response()->json([
                'error' => 'Federation not found.',
            ], 404);
        }

        return new FederationResource($federation);
    }
}
